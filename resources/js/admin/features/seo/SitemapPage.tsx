import type { ReactNode } from 'react';
import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Check, ExternalLink, TriangleAlert, X } from 'lucide-react';
import { ApiError, api, getData } from '@shared/api/client';
import { relativeTime } from '@shared/format/time';
import { Badge, Button, Card, ErrorState, Skeleton, Toggle, buttonClasses, cn, useToast } from '@shared/ui';
import { PageHeader } from '@admin/components/PageHeader';
import { robotsLineTone } from './format';

interface Group {
    key: 'page' | 'condition' | 'post' | 'images';
    label: string;
    setting: string;
    enabled: boolean;
    count: number;
}

interface SitemapPayload {
    groups: Group[];
    sitemap: { url: string; urls: number; updated_at: string | null; read_by_google_at: string | null };
    robots: { text: string; default: string; changed_at: string | null; changed_by: string | null };
    notify: {
        live: boolean;
        indexnow: boolean;
        indexnow_ready: boolean;
        search_console_submit: boolean;
        search_console: { connected: boolean; account: string | null; property: string | null };
        include_lastmod: boolean;
        last_notified_at: string | null;
    };
}

interface RobotsTest {
    allowed: boolean;
    line: number | null;
    rule: string | null;
}

const KEY = ['seo', 'sitemap'] as const;

/** Sitemap priorities as each source sets them (PageSeoSource, ConditionSeoSource, PostSeoSource). */
const CAPTIONS: Record<Group['key'], string> = {
    page: 'priority 0.8–1.0',
    condition: 'priority 0.9',
    post: 'priority 0.6',
    images: 'attached to their pages',
};

/** relativeTime mid-sentence: "just now", "3 h ago", "yesterday", "Oct 7". */
function since(iso: string): string {
    const when = relativeTime(iso);

    return when === 'Now' ? 'just now' : when === 'Yesterday' ? 'yesterday' : when;
}

/** Figma 22: what goes into /sitemap.xml, who hears about changes, and robots.txt. */
export function SitemapPage() {
    const sitemap = useQuery({ queryKey: KEY, queryFn: () => getData<SitemapPayload>('admin/seo-sitemap') });

    return (
        <>
            <PageHeader
                trail={['SEO', 'Sitemap & robots']}
                title="Sitemap &"
                accent="robots."
                description="Tell search engines which pages exist and which areas to skip. The sitemap rebuilds every time you publish."
                actions={
                    sitemap.data && (
                        <a href={sitemap.data.sitemap.url} target="_blank" rel="noreferrer" className={buttonClasses('secondary')}>
                            <ExternalLink className="size-[18px]" aria-hidden="true" />
                            View sitemap
                        </a>
                    )
                }
            />
            {sitemap.isPending && <Skeleton className="h-[560px] w-full rounded-sheet" />}
            {sitemap.isError && <ErrorState title="The sitemap didn’t load" description={sitemap.error instanceof ApiError ? sitemap.error.humanMessage : 'Try again in a moment.'} onRetry={() => void sitemap.refetch()} />}
            {sitemap.data && (
                <div className="grid items-start gap-5 xl:grid-cols-[minmax(0,1fr)_500px]">
                    <div className="flex min-w-0 flex-col gap-5">
                        <SitemapCard data={sitemap.data} />
                        <NotifyCard data={sitemap.data} />
                    </div>
                    <div className="flex min-w-0 flex-col gap-5">
                        <RobotsCard data={sitemap.data} />
                    </div>
                </div>
            )}
        </>
    );
}

/** Saves one sitemap switch at a time; the list refetches with fresh counts. */
function useSwitch() {
    const toast = useToast();
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: (values: Record<string, unknown>) => api.put('admin/seo-settings/sitemap', values),
        onSuccess: () => queryClient.invalidateQueries({ queryKey: KEY }),
        onError: (error) => toast.error('Not saved', error instanceof ApiError ? error.humanMessage : undefined),
    });
}

function SitemapCard({ data }: { data: SitemapPayload }) {
    const save = useSwitch();
    const pending = save.isPending ? (save.variables as Record<string, boolean>) : {};
    const read = data.sitemap.read_by_google_at;

    return (
        <Card className="flex min-w-0 flex-col gap-1 rounded-sheet p-5 sm:p-7">
            <div className="flex flex-wrap items-center justify-between gap-3 pb-3">
                <div className="flex min-w-0 flex-col gap-0.5">
                    <h2 className="text-admin-card-title font-bold text-ink">XML sitemap</h2>
                    <p className="text-admin-small break-all text-ink-soft">
                        {data.sitemap.url.replace(/^https?:\/\//, '')}
                        {data.sitemap.updated_at && ` · rebuilt ${since(data.sitemap.updated_at)}`}
                    </p>
                </div>
                {read ? (
                    <Badge tone="success" dot>
                        Read by Google {new Date(read).toLocaleDateString('en-US', { month: 'short', day: 'numeric' })}
                    </Badge>
                ) : (
                    <Badge dot>{data.notify.search_console.connected ? 'Not read by Google yet' : `${data.sitemap.urls} URLs`}</Badge>
                )}
            </div>
            {data.groups.map((group) => {
                const on = group.setting in pending ? Boolean(pending[group.setting]) : group.enabled;

                return (
                    <Row key={group.key} title={group.label} caption={CAPTIONS[group.key]}>
                        <span className="text-admin-body whitespace-nowrap text-ink-soft">{on ? `${group.count} ${group.key === 'images' ? (group.count === 1 ? 'image' : 'images') : group.count === 1 ? 'URL' : 'URLs'}` : 'Not included'}</span>
                        <Toggle label={<span className="sr-only">Include {group.label.toLowerCase()} in the sitemap</span>} checked={on} disabled={save.isPending} onChange={(event) => save.mutate({ [group.setting]: event.target.checked })} />
                    </Row>
                );
            })}
            <Row title="Team" caption="only shown inside pages">
                <span className="text-admin-body whitespace-nowrap text-ink-soft">Not included</span>
                <Toggle label={<span className="sr-only">Team members have no pages of their own</span>} checked={false} disabled readOnly />
            </Row>
        </Card>
    );
}

function NotifyCard({ data }: { data: SitemapPayload }) {
    const save = useSwitch();
    const pending = save.isPending ? (save.variables as Record<string, boolean>) : {};
    const value = (key: 'search_console_submit' | 'indexnow' | 'include_lastmod') => (key in pending ? Boolean(pending[key]) : data.notify[key]);
    const gsc = data.notify.search_console;

    return (
        <Card className="flex min-w-0 flex-col gap-1 rounded-sheet p-5 sm:p-7">
            <h2 className="text-admin-card-title font-bold text-ink">Notify search engines</h2>
            {!data.notify.live && (
                <p className="mt-2 flex items-start gap-2.5 rounded-[12px] bg-paper px-3.5 py-3 text-admin-small text-ink-soft">
                    <TriangleAlert className="mt-px size-4 shrink-0 text-warning" aria-hidden="true" />
                    This is not the live site, so nothing is sent. Search engines hear about changes once SEO_LIVE is on.
                </p>
            )}
            <Row title="Ping Google Search Console on publish" caption={gsc.connected ? `Connected as ${gsc.account ?? gsc.property}` : 'Not connected yet. Your developer adds the Search Console key.'} divider="bottom">
                <Toggle label={<span className="sr-only">Ping Google Search Console on publish</span>} checked={value('search_console_submit')} disabled={save.isPending} onChange={(event) => save.mutate({ search_console_submit: event.target.checked })} />
            </Row>
            <Row title="IndexNow (Bing, Yandex, Seznam)" caption={data.notify.indexnow_ready ? 'Sends changed URLs instantly' : 'Sends changed URLs instantly. Needs an IndexNow key to start.'} divider="bottom">
                <Toggle label={<span className="sr-only">IndexNow</span>} checked={value('indexnow')} disabled={save.isPending} onChange={(event) => save.mutate({ indexnow: event.target.checked })} />
            </Row>
            <Row title="Include last-modified dates" caption="Uses each page’s real update time" divider="bottom">
                <Toggle label={<span className="sr-only">Include last-modified dates</span>} checked={value('include_lastmod')} disabled={save.isPending} onChange={(event) => save.mutate({ include_lastmod: event.target.checked })} />
            </Row>
            {data.notify.last_notified_at && <p className="pt-3 text-admin-small text-ink-soft">Last checked for changes {since(data.notify.last_notified_at)}</p>}
        </Card>
    );
}

function Row({ title, caption, divider = 'top', children }: { title: string; caption: string; divider?: 'top' | 'bottom'; children: ReactNode }) {
    return (
        <div className={cn('flex items-center gap-3.5 border-line py-3', divider === 'top' ? 'border-t' : 'border-b')}>
            <div className="flex min-w-0 flex-1 flex-col gap-px">
                <p className="text-admin-body font-semibold text-ink">{title}</p>
                <p className="text-admin-small text-ink-soft">{caption}</p>
            </div>
            {children}
        </div>
    );
}

const LINE_TONES = { block: 'text-danger', allow: 'text-success', comment: 'text-ink-soft', plain: 'text-ink' } as const;

function RobotsCard({ data }: { data: SitemapPayload }) {
    const toast = useToast();
    const queryClient = useQueryClient();
    const [text, setText] = useState(data.robots.text);
    const [error, setError] = useState<string | null>(null);
    const [path, setPath] = useState('/admin/pages');
    const lines = text.split('\n');
    const dirty = text !== data.robots.text;

    const save = useMutation({
        mutationFn: () => api.put('admin/seo-settings/sitemap', { robots_txt: text }),
        onSuccess: async () => {
            setError(null);
            await queryClient.invalidateQueries({ queryKey: KEY });
            toast.success('robots.txt saved', 'The website serves it within seconds.');
        },
        onError: (failure) => {
            if (failure instanceof ApiError && failure.code === 'VALIDATION_FAILED') setError(failure.fieldErrors.robots_txt?.[0] ?? failure.humanMessage);
            else toast.error('Not saved', failure instanceof ApiError ? failure.humanMessage : undefined);
        },
    });

    // Tests what is in the editor, so a rule can be tried before it is saved.
    const test = useMutation({
        mutationFn: () => api.post<RobotsTest>('admin/seo-sitemap/robots-test', { path, content: text }).then((response) => response.data),
    });

    return (
        <>
            <Card className="flex min-w-0 flex-col overflow-hidden rounded-sheet">
                <div className="flex items-center justify-between gap-3 px-6 py-5">
                    <h2 className="text-admin-card-title font-bold text-ink">robots.txt</h2>
                    {data.notify.live ? (
                        <Badge tone="success" dot>
                            Production
                        </Badge>
                    ) : (
                        <Badge tone="warning" dot>
                            Staging
                        </Badge>
                    )}
                </div>
                <p className="flex items-center gap-2.5 bg-warning-soft px-6 py-3 text-admin-small text-warning">
                    <TriangleAlert className="size-4 shrink-0" aria-hidden="true" />
                    Staging and preview sites always send “Disallow: /” no matter what is saved here.
                </p>
                <div className="relative bg-paper px-6 py-[18px] font-mono text-[13px] leading-[1.45]">
                    {/* The coloured copy sits under a transparent textarea that wraps the same way. */}
                    <div aria-hidden="true" className="flex flex-col">
                        {lines.map((line, index) => (
                            <div key={index} className="grid grid-cols-[22px_minmax(0,1fr)] gap-3.5">
                                <span className="text-right text-[12px] text-ink-soft/60 select-none">{index + 1}</span>
                                <span className={cn('break-all whitespace-pre-wrap', LINE_TONES[robotsLineTone(line)])}>{line === '' ? ' ' : line}</span>
                            </div>
                        ))}
                    </div>
                    <textarea
                        aria-label="robots.txt"
                        value={text}
                        spellCheck={false}
                        maxLength={5000}
                        onChange={(event) => {
                            setText(event.target.value);
                            setError(null);
                            test.reset();
                        }}
                        aria-invalid={error ? true : undefined}
                        className="absolute inset-y-[18px] right-6 left-[calc(24px+22px+14px)] resize-none overflow-hidden bg-transparent p-0 font-mono text-[13px] leading-[1.45] break-all whitespace-pre-wrap text-transparent caret-ink outline-none selection:bg-royal/20"
                    />
                </div>
                {error && <p className="px-6 pt-3 text-admin-small text-danger">{error}</p>}
                <div className="flex flex-wrap items-center justify-between gap-3 px-6 py-3.5">
                    <p className="text-admin-small text-ink-soft">{data.robots.changed_at ? `Last changed ${since(data.robots.changed_at)}${data.robots.changed_by ? ` by ${data.robots.changed_by}` : ''}` : 'The default rules, never changed'}</p>
                    <div className="flex gap-2">
                        <Button variant="ghost" size="sm" className="text-ink-soft" disabled={text === data.robots.default} onClick={() => setText(data.robots.default)}>
                            Reset to default
                        </Button>
                        <Button variant="navy" size="sm" loading={save.isPending} disabled={!dirty} onClick={() => save.mutate()}>
                            Save
                        </Button>
                    </div>
                </div>
            </Card>

            <Card className="flex min-w-0 flex-col gap-3 rounded-sheet p-6">
                <div className="flex flex-col gap-0.5">
                    <h2 className="text-admin-card-title font-bold text-ink">Test a URL</h2>
                    <p className="text-admin-small text-ink-soft">Check if Googlebot is allowed</p>
                </div>
                <form
                    className="flex items-end gap-2.5"
                    onSubmit={(event) => {
                        event.preventDefault();
                        if (path.trim() !== '') test.mutate();
                    }}
                >
                    <input
                        aria-label="Address to test"
                        value={path}
                        maxLength={500}
                        placeholder="/tms"
                        onChange={(event) => {
                            setPath(event.target.value);
                            test.reset();
                        }}
                        className="h-11 min-w-0 flex-1 rounded-control border border-line bg-white px-3.5 text-admin-body text-ink focus:border-royal focus:ring-2 focus:ring-royal/20 focus:outline-none"
                    />
                    <Button type="submit" variant="secondary" loading={test.isPending}>
                        Test
                    </Button>
                </form>
                <div aria-live="polite">
                    {test.data && (
                        <p className={cn('flex items-center gap-2.5 rounded-[12px] px-3.5 py-3 text-admin-body font-semibold', test.data.allowed ? 'bg-success-soft text-success' : 'bg-danger-soft text-danger')}>
                            {test.data.allowed ? <Check className="size-4 shrink-0" aria-hidden="true" /> : <X className="size-4 shrink-0" aria-hidden="true" />}
                            {test.data.allowed ? (test.data.line ? `Allowed by line ${test.data.line} · ${test.data.rule}` : 'Allowed · no rule blocks it') : `Blocked by line ${test.data.line} · ${test.data.rule}`}
                        </p>
                    )}
                    {test.isError && <p className="text-admin-small text-danger">{test.error instanceof ApiError ? test.error.humanMessage : 'The test didn’t run. Try again.'}</p>}
                </div>
            </Card>
        </>
    );
}
