import type { FormEvent, ReactNode } from 'react';
import { useMemo, useState } from 'react';
import { useSearchParams } from 'react-router';
import { ChevronDown, CircleCheck, HelpCircle, Trash2, TriangleAlert } from 'lucide-react';
import { ApiError } from '@shared/api/client';
import { firstErrors, type Errors } from '@shared/forms/validate';
import { Badge, Button, ConfirmDialog, EmptyState, ErrorState, SearchInput, SortableList, TextInput, Textarea, Toggle, cn, useToast } from '@shared/ui';
import { FormAlert } from '@admin/features/auth/FormAlert';
import { useCollectionItems, useDeleteItem, useSaveItem } from './api';
import { faqSchemaReport } from './faqSchema';
import { ReadOnlyNote } from './ItemShell';
import { useCollectionAccess } from './permissions';
import { ListSkeleton } from './TeamPanel';
import { groupLabel, type Faq } from './types';
import { useLocalOrder } from './useLocalOrder';

/** Figma 15: FAQs grouped by the page that shows them. */
export function FaqPanel({ creating, onCreated }: { creating: boolean; onCreated: () => void }) {
    const [params, setParams] = useSearchParams();
    const list = useCollectionItems<Faq>('faqs');
    const access = useCollectionAccess();
    const [search, setSearch] = useState('');
    const [open, setOpen] = useState<number | null>(null);
    const all = useMemo(() => list.data?.items ?? [], [list.data]);

    const groups = useMemo(() => {
        const counts = new Map<string, number>((list.data?.groups ?? []).map((group) => [group.value, group.count]));
        const wanted = params.get('group');

        if (wanted && !counts.has(wanted)) counts.set(wanted, 0);
        if (counts.size === 0) counts.set('home', 0);

        return [...counts.entries()].map(([value, count]) => ({
            value,
            count,
        }));
    }, [list.data, params]);

    const group = params.get('group') ?? groups[0]?.value ?? 'home';
    const inGroup = useMemo(() => all.filter((faq) => faq.group === group), [all, group]);
    const order = useLocalOrder('faqs', inGroup, group);
    const term = search.trim().toLowerCase();
    const visible = term === '' ? order.order : order.order.filter((faq) => `${faq.question} ${faq.answer}`.toLowerCase().includes(term));
    const report = faqSchemaReport(group, all);

    if (list.isPending) return <ListSkeleton />;
    if (list.isError) return <ErrorState title="FAQs didn’t load" description="Check your connection and try again." onRetry={() => void list.refetch()} />;

    const selectGroup = (value: string) => {
        setOpen(null);
        setParams((current) => {
            const next = new URLSearchParams(current);
            next.set('group', value);

            return next;
        });
    };

    return (
        <div className="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_320px]">
            <div className="flex min-w-0 flex-col gap-4">
                <div className="flex flex-wrap items-center gap-3">
                    <div role="tablist" aria-label="FAQ group" className="flex max-w-full gap-1 overflow-x-auto rounded-full border border-line bg-white p-1">
                        {groups.map((item) => (
                            <button
                                key={item.value}
                                type="button"
                                role="tab"
                                aria-selected={item.value === group}
                                onClick={() => selectGroup(item.value)}
                                className={cn(
                                    'shrink-0 rounded-full px-3.5 py-1.5 text-admin-body font-semibold whitespace-nowrap transition-colors',
                                    item.value === group ? 'bg-navy text-white' : 'text-ink-soft hover:bg-paper hover:text-ink'
                                )}
                            >
                                {groupLabel(item.value)} · {item.count}
                            </button>
                        ))}
                    </div>
                    <SearchInput
                        label="Search questions"
                        placeholder="Search questions"
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                        className="min-w-56 flex-1"
                    />
                </div>

                {creating && (
                    <div className="rounded-card border-[1.5px] border-royal bg-white">
                        <FaqEditor faq={null} group={group} onDone={onCreated} />
                    </div>
                )}

                {visible.length === 0 && !creating ? (
                    <EmptyState
                        icon={<HelpCircle className="size-5" />}
                        title={term ? 'No questions match' : 'No questions in this group yet'}
                        description={term ? 'Try another word.' : 'Add the questions patients ask most on the phone.'}
                    />
                ) : (
                    <SortableList
                        items={visible}
                        disabled={!access.mayPublish || term !== ''}
                        getKey={(faq) => faq.id}
                        getLabel={(faq) => faq.question}
                        onMove={order.move}
                        className="gap-3"
                        renderItem={(faq, _index, handle) => {
                            const expanded = open === faq.id;

                            return (
                                <div className={cn('rounded-card border bg-white transition-colors', expanded ? 'border-[1.5px] border-royal' : 'border-line')}>
                                    <div className="flex items-center gap-3 px-5 py-3.5">
                                        {handle ?? <span className="w-1" />}
                                        <button
                                            type="button"
                                            aria-expanded={expanded}
                                            onClick={() => setOpen(expanded ? null : faq.id)}
                                            className="flex min-w-0 flex-1 items-center gap-3 rounded-control py-1 text-left"
                                        >
                                            <span className="min-w-0 flex-1 text-[15px] font-semibold text-ink">{faq.question}</span>
                                            {!faq.is_published && <Badge tone="neutral">Hidden</Badge>}
                                            {faq.is_published && !faq.in_schema && <Badge tone="warning">Not in schema</Badge>}
                                            <Badge tone="neutral">{groupLabel(faq.group)}</Badge>
                                            <ChevronDown
                                                className={cn('size-4 shrink-0 text-ink-soft transition-transform', expanded ? 'rotate-180' : '-rotate-90')}
                                                aria-hidden="true"
                                            />
                                        </button>
                                    </div>
                                    {expanded && <FaqEditor faq={faq} group={group} onDone={() => setOpen(null)} />}
                                </div>
                            );
                        }}
                    />
                )}
            </div>

            <aside className="flex flex-col gap-5 xl:sticky xl:top-6">
                <div className="flex flex-col gap-4 rounded-sheet border border-line bg-white p-6">
                    <div className="flex items-start justify-between gap-3">
                        <div>
                            <h2 className="text-admin-card-title font-semibold text-ink">FAQ schema</h2>
                            <p className="text-admin-small text-ink-soft">{groupLabel(group)} page</p>
                        </div>
                        <Badge tone={report.valid ? 'success' : 'warning'} dot>
                            {report.valid ? 'Valid' : 'Check'}
                        </Badge>
                    </div>
                    <ul className="flex flex-col gap-3.5 text-admin-body text-ink">
                        <Check ok={report.inMarkup > 0}>
                            {report.inMarkup} {report.inMarkup === 1 ? 'question' : 'questions'} in FAQPage markup
                        </Check>
                        <Check ok={report.duplicates.length === 0}>
                            {report.duplicates.length === 0 ? 'No duplicate questions across pages' : `${report.duplicates.length} question(s) repeated on another page`}
                        </Check>
                        <Check ok={report.longAnswers.length === 0}>
                            {report.longAnswers.length === 0 ? 'Answers under 300 words' : `${report.longAnswers.length} answer(s) over 300 words`}
                        </Check>
                    </ul>
                </div>
                <div className="flex flex-col gap-2.5 rounded-sheet bg-sky-soft p-6">
                    <h2 className="text-admin-card-title font-semibold text-navy">Writing tip</h2>
                    <p className="text-[15px] leading-[1.55] text-ink-soft">Phrase questions the way patients ask them on the phone. Those words are what people search for.</p>
                </div>
            </aside>
        </div>
    );
}

function Check({ ok, children }: { ok: boolean; children: ReactNode }) {
    return (
        <li className="flex items-start gap-3">
            {ok ? (
                <CircleCheck className="mt-0.5 size-4 shrink-0 text-success" aria-hidden="true" />
            ) : (
                <TriangleAlert className="mt-0.5 size-4 shrink-0 text-warning" aria-hidden="true" />
            )}
            <span>{children}</span>
        </li>
    );
}

function FaqEditor({ faq, group, onDone }: { faq: Faq | null; group: string; onDone: () => void }) {
    const toast = useToast();
    const access = useCollectionAccess();
    const save = useSaveItem<Faq>('faqs');
    const remove = useDeleteItem('faqs');
    const [question, setQuestion] = useState(faq?.question ?? '');
    const [answer, setAnswer] = useState(faq?.answer ?? '');
    const [inSchema, setInSchema] = useState(faq?.in_schema ?? true);
    const [published, setPublished] = useState(faq?.is_published ?? false);
    const [errors, setErrors] = useState<Errors>({});
    const [alert, setAlert] = useState<string | null>(null);
    const [confirming, setConfirming] = useState(false);
    const editable = access.mayChange(faq);

    const submit = (event: FormEvent) => {
        event.preventDefault();
        setAlert(null);

        save.mutate(
            {
                id: faq?.id ?? null,
                body: {
                    group: faq?.group ?? group,
                    question: question.trim(),
                    answer: answer.trim(),
                    in_schema: inSchema,
                    ...(access.mayPublish ? { is_published: published } : {}),
                },
            },
            {
                onSuccess: () => {
                    toast.success(faq ? 'Question saved' : 'Question added', published ? 'The website updates within seconds.' : 'Hidden until published.');
                    onDone();
                },
                onError: (error) => {
                    if (!(error instanceof ApiError)) return;
                    setErrors(firstErrors(error.fieldErrors));
                    setAlert(error.code === 'VALIDATION_FAILED' ? 'Check the highlighted fields.' : error.humanMessage);
                },
            }
        );
    };

    return (
        <form onSubmit={submit} noValidate className="flex flex-col gap-4 border-t border-line px-5 pt-4 pb-5">
            {alert && <FormAlert>{alert}</FormAlert>}
            {!editable && <ReadOnlyNote>This answer is live. Changing it needs the content.publish permission.</ReadOnlyNote>}
            <fieldset disabled={!editable} className="flex min-w-0 flex-col gap-4">
                <TextInput label="Question" maxLength={200} value={question} onChange={(e) => setQuestion(e.target.value)} error={errors.question} autoFocus={faq === null} />
                <Textarea
                    label="Answer"
                    rows={4}
                    maxLength={800}
                    value={answer}
                    onChange={(e) => setAnswer(e.target.value)}
                    error={errors.answer}
                    help="Plain language. Link to sources where helpful: [text](/path)."
                />
                <div className="flex flex-wrap items-center gap-x-6 gap-y-3">
                    <Toggle label="Include in FAQ schema" checked={inSchema} onChange={(e) => setInSchema(e.target.checked)} />
                    <Toggle label="Published" checked={published} disabled={!access.mayPublish} onChange={(e) => setPublished(e.target.checked)} />
                    <div className="ml-auto flex items-center gap-2">
                        {faq ? (
                            <Button variant="ghost" icon={<Trash2 className="size-4" />} onClick={() => setConfirming(true)} disabled={!editable}>
                                Delete
                            </Button>
                        ) : (
                            <Button variant="ghost" onClick={onDone}>
                                Cancel
                            </Button>
                        )}
                        <Button type="submit" variant="navy" loading={save.isPending} disabled={!editable}>
                            Save
                        </Button>
                    </div>
                </div>
                {!access.mayPublish && <p className="text-admin-small text-ink-soft">New questions stay hidden until someone with publishing rights turns them on.</p>}
            </fieldset>
            {faq && (
                <ConfirmDialog
                    open={confirming}
                    title="Delete this question?"
                    description={faq.is_published ? 'It disappears from the website and its FAQ schema at once.' : 'The hidden draft is removed.'}
                    confirmLabel="Delete question"
                    busy={remove.isPending}
                    onClose={() => setConfirming(false)}
                    onConfirm={() =>
                        remove.mutate(faq.id, {
                            onSuccess: () => {
                                toast.success('Question deleted');
                                onDone();
                            },
                            onError: (error) => {
                                setConfirming(false);
                                setAlert(error instanceof ApiError ? error.humanMessage : 'That didn’t work.');
                            },
                        })
                    }
                />
            )}
        </form>
    );
}
