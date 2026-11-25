import { useEffect, useState } from 'react';
import { CalendarClock, CalendarX, Check, ChevronDown, CircleSlash, Eye, RefreshCw, TriangleAlert } from 'lucide-react';
import { useBlocker, useParams, useSearchParams } from 'react-router';
import { ApiError } from '@shared/api/client';
import { can } from '@shared/auth/can';
import { Button, ConfirmDialog, Count, Dialog, Dropdown, ErrorState, SegmentedTabs, Skeleton, useToast, type DropdownItem } from '@shared/ui';
import { useCurrentUser } from '@admin/app/AuthProvider';
import { siteUrl } from '@admin/lib/content';
import { pageRequests, useBlockTypes, usePage } from './api';
import { ContentTab } from './ContentTab';
import { saveStatus } from './editorState';
import { RevisionsTab } from './RevisionsTab';
import { ScheduleDialog } from './ScheduleDialog';
import { SettingsTab } from './SettingsTab';
import { StatusBadge, shortDateTime } from './statusBadge';
import type { BlockTypeDef, PageDetail } from './types';
import { useEditor } from './useEditor';
import { SeoWorkspace } from '@admin/features/seo/SeoWorkspace';

type Tab = 'content' | 'seo' | 'social' | 'schema' | 'settings' | 'revisions';

/** Figma 07: the page editor. Loads the page and block library, then hands over to the editor. */
export function PageEditorPage() {
    const id = Number(useParams().id);
    const page = usePage(id);
    const types = useBlockTypes();

    if (page.isError || types.isError) {
        const error = page.error ?? types.error;

        return (
            <ErrorState
                title={error instanceof ApiError && error.code === 'NOT_FOUND' ? 'This page doesn’t exist' : 'The page didn’t load'}
                description={error instanceof ApiError ? error.humanMessage : 'Try again in a moment.'}
                requestId={error instanceof ApiError ? error.requestId : null}
                onRetry={() => {
                    void page.refetch();
                    void types.refetch();
                }}
            />
        );
    }

    if (page.isPending || types.isPending) {
        return (
            <div className="flex flex-col gap-6" aria-busy="true" aria-label="Loading the page">
                <Skeleton className="h-4 w-40" />
                <Skeleton className="h-10 w-80" />
                <div className="grid gap-6 xl:grid-cols-[232px_1fr_288px]">
                    <Skeleton className="h-[520px] w-full rounded-sheet" />
                    <Skeleton className="h-[520px] w-full rounded-sheet" />
                    <Skeleton className="h-[320px] w-full rounded-sheet" />
                </div>
            </div>
        );
    }

    return <Editor key={page.data.id} page={page.data} types={types.data} />;
}

function Editor({ page, types }: { page: PageDetail; types: BlockTypeDef[] }) {
    const user = useCurrentUser();
    const toast = useToast();
    const editor = useEditor(page);
    const [params, setParams] = useSearchParams();
    const tab = (params.get('tab') as Tab | null) ?? 'content';
    const [selected, setSelected] = useState<string | null>(editor.blocks[0]?.key ?? null);
    const [busy, setBusy] = useState<null | 'draft' | 'publish' | 'schedule' | 'unschedule' | 'unpublish' | 'preview'>(null);
    const [scheduling, setScheduling] = useState(false);
    const [scheduleError, setScheduleError] = useState<string | null>(null);
    const [unpublishing, setUnpublishing] = useState(false);
    const [now, setNow] = useState(() => Date.now());
    const mayPublish = can(user, 'content.publish');
    const { meta } = editor;

    useEffect(() => {
        const timer = window.setInterval(() => setNow(Date.now()), 5000);

        return () => window.clearInterval(timer);
    }, []);

    // After an explicit save is refused, jump to the first section with errors
    // (autosave never moves the selection while someone is typing).
    const showFirstError = () => {
        const key = editor.firstErrorKey();
        if (key) setSelected(key);
    };

    // Leaving with unsaved edits: browser tab close and in-app navigation.
    const pending = editor.dirty || editor.saving;
    useEffect(() => {
        if (!pending) return;
        const warn = (event: BeforeUnloadEvent) => event.preventDefault();
        window.addEventListener('beforeunload', warn);

        return () => window.removeEventListener('beforeunload', warn);
    }, [pending]);
    const blocker = useBlocker(({ currentLocation, nextLocation }) => pending && currentLocation.pathname !== nextLocation.pathname);

    const fail = (title: string, error: unknown) => toast.error(title, error instanceof ApiError ? error.humanMessage : undefined);

    const saveDraft = async () => {
        setBusy('draft');
        if (await editor.save(true)) toast.success('Draft saved', 'A revision was kept. The website is unchanged.');
        else if (editor.firstErrorKey()) {
            showFirstError();
            toast.error('Draft not saved', 'Fix the highlighted fields and try again.');
        } else if (!editor.conflict) toast.error('Draft not saved', 'Try again in a moment.');
        setBusy(null);
    };

    const publish = async () => {
        setBusy('publish');

        try {
            if (!(await editor.save())) {
                showFirstError();
                toast.error('Not published', editor.firstErrorKey() ? 'Fix the highlighted fields first.' : 'Your changes couldn’t be saved.');

                return;
            }

            const response = await pageRequests.publish(meta.id, editor.lockVersion());
            editor.applyPage(response.data, false);
            toast.success('Published', 'The website updates within seconds.');
        } catch (error) {
            fail('Not published', error);
        } finally {
            setBusy(null);
        }
    };

    const schedule = async (iso: string) => {
        setBusy('schedule');
        setScheduleError(null);

        try {
            if (!(await editor.save())) {
                showFirstError();
                throw new Error('Fix the highlighted fields first.');
            }
            const response = await pageRequests.schedule(meta.id, iso, editor.lockVersion());
            editor.applyPage(response.data, false);
            setScheduling(false);
            toast.success('Scheduled', `Goes live ${shortDateTime(iso)}.`);
        } catch (error) {
            setScheduleError(error instanceof ApiError ? (error.fieldErrors.at?.[0] ?? error.humanMessage) : error instanceof Error ? error.message : 'That didn’t work.');
        } finally {
            setBusy(null);
        }
    };

    const simple = async (kind: 'unschedule' | 'unpublish') => {
        setBusy(kind);

        try {
            const response = kind === 'unschedule' ? await pageRequests.unschedule(meta.id) : await pageRequests.unpublish(meta.id);
            editor.applyPage(response.data, false);
            setUnpublishing(false);
            toast.success(kind === 'unschedule' ? 'Schedule cancelled' : 'Unpublished', kind === 'unpublish' ? 'The page is no longer on the website.' : undefined);
        } catch (error) {
            fail(kind === 'unschedule' ? 'Schedule not cancelled' : 'Not unpublished', error);
        } finally {
            setBusy(null);
        }
    };

    const preview = async () => {
        // Open synchronously so pop-up blockers allow it, then point it at the signed link.
        const win = window.open('about:blank', '_blank');
        setBusy('preview');

        try {
            await editor.save();
            const response = await pageRequests.preview(meta.id);
            if (win) win.location.href = response.data.url;
            else window.open(response.data.url, '_blank', 'noopener');
        } catch (error) {
            win?.close();
            fail('Preview unavailable', error);
        } finally {
            setBusy(null);
        }
    };

    const more: DropdownItem[] = [{ label: 'Schedule…', icon: <CalendarClock className="size-4" aria-hidden="true" />, onSelect: () => setScheduling(true) }];
    if (meta.scheduled_at) more.push({ label: 'Cancel schedule', icon: <CalendarX className="size-4" aria-hidden="true" />, onSelect: () => void simple('unschedule') });
    if (meta.status === 'published' && !meta.is_home) more.push({ label: 'Unpublish', icon: <CircleSlash className="size-4" aria-hidden="true" />, tone: 'danger', onSelect: () => setUnpublishing(true) });

    const live = siteUrl(meta.path);

    return (
        <>
            <header className="flex w-full flex-col gap-3">
                <p className="flex items-center gap-2.5 font-display text-admin-overline font-bold tracking-[1.1px] text-ink-soft uppercase">
                    <span aria-hidden="true" className="h-[1.5px] w-7 bg-royal" />
                    <span className="sr-only">You are in: </span>
                    Pages  /  {meta.title}
                </p>
                <div className="flex flex-wrap items-center justify-between gap-x-8 gap-y-4">
                    <div className="flex min-w-0 flex-wrap items-center gap-x-4 gap-y-2">
                        <h1 className="min-w-0 truncate font-serif text-admin-hero text-ink">{meta.title}</h1>
                        <StatusBadge status={meta.status} />
                        <p className="text-admin-small text-ink-soft" aria-live="polite">
                            {meta.scheduled_at && `Goes live ${shortDateTime(meta.scheduled_at)} · `}
                            {saveStatus({ dirty: editor.dirty, saving: editor.saving, savedAt: editor.savedAt, error: editor.saveError, invalid: Object.keys(editor.errors).length }, now)}
                        </p>
                    </div>
                    <div className="flex flex-wrap items-center gap-3">
                        {live && meta.status === 'published' && (
                            <a href={live} target="_blank" rel="noreferrer" className="hidden text-admin-body font-semibold text-royal hover:text-royal-deep 2xl:inline">
                                View live
                            </a>
                        )}
                        <Button variant="secondary" icon={<Eye className="size-4" aria-hidden="true" />} loading={busy === 'preview'} onClick={() => void preview()}>
                            Preview
                        </Button>
                        <Button variant="ghost" loading={busy === 'draft'} disabled={editor.conflict} onClick={() => void saveDraft()}>
                            Save draft
                        </Button>
                        {mayPublish && (
                            <div className="flex items-center">
                                <Button
                                    className="rounded-r-none pr-4"
                                    icon={<Check className="size-4" aria-hidden="true" />}
                                    loading={busy === 'publish'}
                                    disabled={editor.conflict || (meta.status === 'published' && !meta.has_unpublished_changes && !editor.dirty)}
                                    onClick={() => void publish()}
                                >
                                    {meta.status === 'published' ? 'Publish changes' : 'Publish'}
                                </Button>
                                <Dropdown
                                    items={more}
                                    trigger={({ ref, ...props }) => (
                                        <button ref={ref} type="button" {...props} aria-label="More publishing options" disabled={editor.conflict} className="flex h-10 items-center rounded-r-full border-l border-white/30 bg-royal pr-3.5 pl-2.5 text-white hover:bg-royal-deep disabled:opacity-50">
                                            <ChevronDown className="size-4" aria-hidden="true" />
                                        </button>
                                    )}
                                />
                            </div>
                        )}
                    </div>
                </div>
            </header>

            {editor.conflict && (
                <div role="alert" className="flex flex-wrap items-center justify-between gap-4 rounded-card bg-danger-soft px-6 py-4 text-admin-body text-danger">
                    <p className="flex items-start gap-2.5">
                        <TriangleAlert className="mt-0.5 size-4 shrink-0" aria-hidden="true" />
                        Someone else saved this page while you were editing. Your latest changes are not saved. Reload to see their version.
                    </p>
                    <Button variant="danger" size="sm" icon={<RefreshCw className="size-3.5" aria-hidden="true" />} onClick={() => window.location.reload()}>
                        Reload
                    </Button>
                </div>
            )}
            {editor.generalErrors.length > 0 && (
                <p role="alert" className="rounded-card bg-danger-soft px-6 py-3 text-admin-body text-danger">
                    {editor.generalErrors.join(' ')}
                </p>
            )}

            <SegmentedTabs
                label="Page editor"
                value={tab}
                onChange={(next) => setParams((current) => {
                    const copy = new URLSearchParams(current);
                    if (next === 'content') copy.delete('tab');
                    else copy.set('tab', next);

                    return copy;
                }, { replace: true })}
                options={[
                    { value: 'content', label: 'Content' },
                    { value: 'seo', label: 'SEO' },
                    { value: 'social', label: 'Social' },
                    { value: 'schema', label: 'Schema' },
                    { value: 'settings', label: 'Settings' },
                    {
                        value: 'revisions',
                        label: (
                            <span className="flex items-center gap-2">
                                Revisions <Count active={tab === 'revisions'}>{meta.revisions_count}</Count>
                            </span>
                        ),
                    },
                ]}
            />

            {tab === 'content' && <ContentTab editor={editor} types={types} selected={selected} onSelect={setSelected} onOpenPreview={() => void preview()} />}
            {(tab === 'seo' || tab === 'social' || tab === 'schema') && <SeoWorkspace type="page" id={meta.id} tab={tab} />}
            {tab === 'settings' && <SettingsTab editor={editor} canChange={meta.status === 'draft' || mayPublish} />}
            {tab === 'revisions' && <RevisionsTab editor={editor} />}

            <ScheduleDialog open={scheduling} busy={busy === 'schedule'} error={scheduleError} onClose={() => setScheduling(false)} onSchedule={(iso) => void schedule(iso)} />
            <ConfirmDialog
                open={unpublishing}
                title={`Unpublish “${meta.title}”?`}
                description="The page comes off the website now. Its content stays here as a draft, and menu links to it are hidden."
                confirmLabel="Unpublish"
                busy={busy === 'unpublish'}
                onClose={() => setUnpublishing(false)}
                onConfirm={() => void simple('unpublish')}
            />
            <Dialog
                open={blocker.state === 'blocked'}
                onClose={() => blocker.reset?.()}
                title="Leave with unsaved changes?"
                description="Your latest edits haven’t reached the server yet."
                footer={
                    <>
                        <Button variant="ghost" onClick={() => blocker.proceed?.()}>
                            Leave anyway
                        </Button>
                        <Button
                            onClick={() =>
                                void editor.save().then((ok) => {
                                    if (ok) blocker.proceed?.();
                                    else blocker.reset?.();
                                })
                            }
                        >
                            Save and leave
                        </Button>
                    </>
                }
            />
        </>
    );
}
