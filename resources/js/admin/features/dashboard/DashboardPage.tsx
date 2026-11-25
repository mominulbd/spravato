import type { ReactNode } from 'react';
import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { ArrowRight, FilePlus2, Inbox, TrendingDown, TrendingUp } from 'lucide-react';
import { Link } from 'react-router';
import { ApiError, getData } from '@shared/api/client';
import { can } from '@shared/auth/can';
import { Badge, ButtonLink, Card, DataTable, EmptyState, ErrorState, Skeleton, cn, type Column } from '@shared/ui';
import { useCurrentUser } from '@admin/app/AuthProvider';
import { useConsultationOverview } from '@admin/features/consultations/api';
import { receivedLabel, shortStaffName, statusBadge, waitingLabel } from '@admin/features/consultations/format';
import type { ConsultationOverview, ConsultationRow } from '@admin/features/consultations/types';
import { usePages } from '@admin/features/pages/api';
import { StatusBadge } from '@admin/features/pages/statusBadge';
import { useSeoAudit } from '@admin/features/seo/api';
import { ScoreRing } from '@admin/features/seo/parts';
import type { SeoAuditOverview } from '@admin/features/seo/types';
import { RequestsChart, TopicShare } from './RequestsChart';

const RANGES = [7, 30, 90] as const;

/** Figma 03: the call-back queue first, then requests, content and search. */
export function DashboardPage() {
    const user = useCurrentUser();
    const seesRequests = can(user, 'consultations.view');
    const editsContent = can(user, 'content.edit');
    const seesSeo = can(user, 'seo.edit') || can(user, 'seo.manage');
    const seo = useSeoAudit(seesSeo);
    const [range, setRange] = useState<(typeof RANGES)[number]>(30);
    const overview = useConsultationOverview(range, seesRequests);
    const now = new Date();
    const firstName = user.name.split(' ')[0] ?? user.name;

    return (
        <>
            <header className="flex w-full flex-col gap-3">
                <p className="flex items-center gap-2.5 font-display text-admin-overline font-bold tracking-[1.1px] text-ink-soft uppercase">
                    <span aria-hidden="true" className="h-[1.5px] w-7 bg-royal" />
                    Overview · {now.toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric' })}
                </p>
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <h1 className="font-serif text-admin-hero text-ink">
                        {greeting(now)}, <em className="text-royal">{firstName}.</em>
                    </h1>
                    {editsContent && (
                        <ButtonLink to="/pages?new=1" variant="secondary" icon={<FilePlus2 className="size-[18px]" aria-hidden="true" />}>
                            New page
                        </ButtonLink>
                    )}
                </div>
            </header>

            {seesRequests && <CallbackQueue overview={overview.data} loading={overview.isPending} error={overview.error} onRetry={() => void overview.refetch()} />}

            <Metrics overview={seesRequests ? overview.data : undefined} showRequests={seesRequests} showPages={editsContent} seo={seesSeo ? (seo.data ?? null) : undefined} range={range} />

            {seesRequests && overview.data && (
                <div className="grid gap-5 xl:grid-cols-[minmax(0,1fr)_360px]">
                    <Card className="flex min-w-0 flex-col justify-between gap-5 rounded-sheet p-6">
                        <div className="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <h2 className="text-admin-card-title font-bold text-ink">Consultation requests</h2>
                                <p className="text-admin-small text-ink-soft">Daily form submissions</p>
                            </div>
                            <div role="radiogroup" aria-label="Period" className="flex rounded-full bg-paper p-1">
                                {RANGES.map((days) => (
                                    <button
                                        key={days}
                                        type="button"
                                        role="radio"
                                        aria-checked={range === days}
                                        onClick={() => setRange(days)}
                                        className={cn('h-7 rounded-full px-3 text-admin-small font-semibold transition-colors', range === days ? 'bg-navy text-white' : 'text-ink-soft hover:text-ink')}
                                    >
                                        {days} days
                                    </button>
                                ))}
                            </div>
                        </div>
                        <RequestsChart days={overview.data.daily} />
                    </Card>
                    <Card className="flex min-w-0 flex-col gap-5 rounded-sheet p-6">
                        <div>
                            <h2 className="text-admin-card-title font-bold text-ink">What people ask about</h2>
                            <p className="text-admin-small text-ink-soft">Last {range} days</p>
                        </div>
                        <TopicShare topics={overview.data.topics} />
                    </Card>
                </div>
            )}

            <div className={cn('grid gap-5', seesRequests && (editsContent || seesSeo) && 'xl:grid-cols-[minmax(0,1fr)_360px]')}>
                {seesRequests && <RecentRequests rows={overview.data?.recent} loading={overview.isPending} />}
                {(editsContent || seesSeo) && (
                    <div className="flex min-w-0 flex-col gap-5">
                        {seesSeo && <SeoTodo data={seo.data} loading={seo.isPending} />}
                        {editsContent && <PublishingQueue />}
                    </div>
                )}
            </div>
        </>
    );
}

function greeting(now: Date): string {
    const hour = now.getHours();

    return hour < 12 ? 'Good morning' : hour < 17 ? 'Good afternoon' : 'Good evening';
}

const NUMBER_WORDS = ['No one', 'One person', 'Two people', 'Three people', 'Four people', 'Five people', 'Six people', 'Seven people', 'Eight people', 'Nine people'];

function peopleWaiting(count: number): string {
    return NUMBER_WORDS[count] ?? `${count} people`;
}

interface PublicHours {
    hours: { days: { day: number; is_open: boolean; display: string }[] };
}

/** "Office hours today · 8:00 AM–5:00 PM", from the website's own settings. */
function useHoursToday(): string | null {
    const settings = useQuery({ queryKey: ['public-settings'], queryFn: () => getData<PublicHours>('/api/v1/public/settings'), staleTime: 10 * 60_000 });
    const isoDay = ((new Date().getDay() + 6) % 7) + 1;
    const today = settings.data?.hours.days.find((day) => day.day === isoDay);

    if (!today) return null;

    return today.is_open ? `Office hours today · ${today.display.replace(' – ', '–')}` : 'The clinic is closed today';
}

function CallbackQueue({ overview, loading, error, onRetry }: { overview: ConsultationOverview | undefined; loading: boolean; error: Error | null; onRetry: () => void }) {
    const hours = useHoursToday();

    if (error) {
        return <ErrorState title="The call-back queue didn’t load" description={error instanceof ApiError ? error.humanMessage : 'Try again in a moment.'} onRetry={onRetry} />;
    }

    if (loading || !overview) {
        return <Skeleton className="h-[280px] w-full rounded-sheet" />;
    }

    const { waiting, waiting_over_day: overDay, longest } = overview.queue;

    return (
        <section aria-labelledby="queue-title" className="grid gap-8 rounded-sheet bg-navy p-8 text-white lg:grid-cols-[minmax(0,1fr)_minmax(0,370px)] lg:p-10">
            <div className="flex min-w-0 flex-col justify-center gap-4">
                <p className="font-display text-[11px] leading-[1.3] font-bold tracking-[1.1px] text-white/70 uppercase">Consultation inbox</p>
                <h2 id="queue-title" className="font-serif text-[34px] leading-[1.12] tracking-[-0.02em] md:text-[38px]">
                    {waiting === 0 ? (
                        <>
                            No one is waiting for <em className="text-sky">a call back.</em>
                        </>
                    ) : (
                        <>
                            {peopleWaiting(waiting)} {waiting === 1 ? 'is' : 'are'} waiting for <em className="text-sky">a call back.</em>
                        </>
                    )}
                </h2>
                <p className="max-w-[460px] text-[15px] leading-[1.6] text-white/80">
                    {waiting === 0
                        ? 'New requests from the website appear here within seconds. Requests are encrypted; open the inbox to see contact details.'
                        : `${overDay === 0 ? 'None has' : overDay === 1 ? 'One has' : `${peopleWaiting(overDay).replace(' people', '')} have`} waited more than 24 hours. Requests are encrypted; open the inbox to see contact details.`}
                </p>
                <div className="flex flex-wrap items-center gap-4">
                    <Link to={waiting > 0 ? '/consultations?filter[status]=new' : '/consultations'} className="inline-flex h-10 items-center gap-2 rounded-full bg-white px-[18px] font-display text-[14px] font-bold text-navy transition-colors hover:bg-sky">
                        <Inbox className="size-[18px]" aria-hidden="true" />
                        Open inbox
                    </Link>
                    {hours && <span className="text-admin-small text-white/70">{hours}</span>}
                </div>
            </div>
            {longest.length > 0 && (
                <div className="flex min-w-0 flex-col gap-2.5">
                    <p className="flex justify-between gap-3 font-display text-[10px] leading-[1.3] font-bold tracking-[1.1px] text-white/60 uppercase">
                        <span>Longest wait</span>
                        <span>Target · 1 business day</span>
                    </p>
                    <ol className="flex flex-col gap-2.5">
                        {longest.map((item) => (
                            <li key={item.id}>
                                <Link to={`/consultations/${item.id}`} className="flex flex-col gap-2.5 rounded-[14px] bg-sidebar-active px-4 py-3.5 transition-colors hover:bg-white/15">
                                    <span className="flex items-baseline justify-between gap-3">
                                        <span className="min-w-0 truncate text-admin-body font-semibold">
                                            {item.topic.label} <span className="ml-1.5 text-admin-small font-normal text-white/60">{item.reference.replace(/^CR-\d{4}-/, 'CR-')}</span>
                                        </span>
                                        <span className="shrink-0 text-admin-body font-semibold">{waitingLabel(item.waiting_minutes)}</span>
                                    </span>
                                    <span className="h-[3px] w-full overflow-hidden rounded-full bg-white/15" role="img" aria-label={item.is_overdue ? 'Past the target' : `${Math.round((item.progress ?? 0) * 100)}% of the target used`}>
                                        <span className={cn('block h-full rounded-full', item.is_overdue ? 'bg-[#e5484d]' : (item.progress ?? 0) >= 0.75 ? 'bg-[#e2a336]' : 'bg-sky')} style={{ width: `${Math.max(4, Math.round((item.progress ?? 0) * 100))}%` }} />
                                    </span>
                                </Link>
                            </li>
                        ))}
                    </ol>
                </div>
            )}
        </section>
    );
}

function Metric({ label, value, aside, note }: { label: string; value: ReactNode; aside?: ReactNode; note: ReactNode }) {
    return (
        <div className="flex min-w-0 flex-col gap-1.5 px-6 py-5 sm:border-l sm:border-line sm:first:border-l-0">
            <p className="text-admin-small text-ink-soft">{label}</p>
            <p className="flex items-baseline gap-2.5">
                <span className="font-serif text-[34px] leading-none tracking-[-0.02em] text-ink">{value}</span>
                {aside}
            </p>
            <p className="text-admin-small text-ink-soft">{note}</p>
        </div>
    );
}

/** `seo`: undefined when the user has no SEO access, null while it loads. */
function Metrics({ overview, showRequests, showPages, seo, range }: { overview: ConsultationOverview | undefined; showRequests: boolean; showPages: boolean; seo: SeoAuditOverview | null | undefined; range: number }) {
    const pages = usePages({ per_page: 1 });
    const counts = showPages ? pages.data?.meta.counts : undefined;
    const change = overview?.new_requests.change_percent ?? null;
    const score = seo?.audit?.score ?? null;
    const previous = seo?.audit?.previous_score ?? null;
    const trend = score !== null && previous !== null ? score - previous : null;

    return (
        <Card className="grid rounded-sheet sm:grid-cols-2 xl:grid-cols-4">
            {showRequests && (
                <Metric
                    label="New requests"
                    value={overview?.new_requests.count ?? '—'}
                    aside={
                        change !== null && (
                            <span className={cn('inline-flex items-center gap-1 text-admin-small font-semibold', change >= 0 ? 'text-success' : 'text-danger')}>
                                {change >= 0 ? <TrendingUp className="size-3.5" aria-hidden="true" /> : <TrendingDown className="size-3.5" aria-hidden="true" />}
                                {Math.abs(change)}%
                            </span>
                        )
                    }
                    note={`vs. previous ${range} days`}
                />
            )}
            {showPages && <Metric label="Published pages" value={counts?.published ?? '—'} aside={counts && <span className="text-admin-small text-ink-soft">{counts.draft} drafts</span>} note={counts ? `${counts.scheduled} scheduled` : ' '} />}
            {seo !== undefined && (
                <>
                    <Metric
                        label="Organic clicks"
                        value={seo?.search ? seo.search.clicks.toLocaleString('en-US') : '—'}
                        note={seo && !seo.connections.search_console ? 'Search Console not connected' : 'Google Search Console · 28 days'}
                    />
                    <Metric
                        label="SEO health"
                        value={score ?? '—'}
                        aside={
                            trend !== null &&
                            trend !== 0 && (
                                <span className={cn('inline-flex items-center gap-1 text-admin-small font-semibold', trend > 0 ? 'text-success' : 'text-danger')}>
                                    {trend > 0 ? <TrendingUp className="size-3.5" aria-hidden="true" /> : <TrendingDown className="size-3.5" aria-hidden="true" />}
                                    {Math.abs(trend)} pts
                                </span>
                            )
                        }
                        note={seo?.audit ? `${seo.open} ${seo.open === 1 ? 'issue' : 'issues'} to review` : 'Site audit runs daily at 6:00'}
                    />
                </>
            )}
        </Card>
    );
}

function RecentRequests({ rows, loading }: { rows: ConsultationRow[] | undefined; loading: boolean }) {
    const columns: Column<ConsultationRow>[] = [
        {
            key: 'request',
            header: 'Request',
            cell: (row) => (
                <Link to={`/consultations/${row.id}`} className="flex min-w-0 flex-col">
                    <span className="truncate font-semibold text-ink hover:text-royal">{row.topic.label}</span>
                    <span className="text-admin-small text-ink-soft">{row.reference}</span>
                </Link>
            ),
        },
        { key: 'received', header: 'Received', className: 'w-[150px]', cell: (row) => <span className="whitespace-nowrap">{receivedLabel(row.received_at)}</span> },
        { key: 'assigned', header: 'Assigned', className: 'w-[120px]', cell: (row) => (row.assigned_to ? shortStaffName(row.assigned_to.name) : <span className="text-ink-soft">Unassigned</span>) },
        {
            key: 'status',
            header: 'Status',
            className: 'w-[136px]',
            cell: (row) => {
                const badge = statusBadge(row);

                return (
                    <Badge tone={badge.tone} dot>
                        {badge.label}
                    </Badge>
                );
            },
        },
    ];

    return (
        <section className="flex min-w-0 flex-col gap-3" aria-labelledby="recent-title">
            <div className="flex items-baseline justify-between gap-3">
                <h2 id="recent-title" className="font-serif text-[24px] leading-[1.2] text-ink">
                    Recent requests
                </h2>
                <Link to="/consultations" className="inline-flex items-center gap-1 text-admin-body font-semibold text-royal hover:underline">
                    View inbox <ArrowRight className="size-4" aria-hidden="true" />
                </Link>
            </div>
            <DataTable
                caption="Recent consultation requests"
                columns={columns}
                rows={rows}
                rowKey={(row) => row.id}
                loading={loading}
                skeletonRows={5}
                empty={<EmptyState icon={<Inbox className="size-5" aria-hidden="true" />} title="No requests yet" description="Requests from the website’s Contact form appear here." />}
            />
        </section>
    );
}

const TODO_DOT = { urgent: 'bg-danger', warning: 'bg-warning', notice: 'bg-royal', passed: 'bg-success' } as const;

/** Figma 03's SEO to-do: the audit's top findings, worst first, and one thing that passed. */
function SeoTodo({ data, loading }: { data: SeoAuditOverview | undefined; loading: boolean }) {
    const open = data?.issues.filter((issue) => issue.severity !== 'passed').slice(0, 4) ?? [];
    const passed = data?.issues.find((issue) => issue.severity === 'passed');
    const shown = open.length < 5 && passed ? [...open, passed] : open;
    const score = data?.audit?.score ?? null;

    return (
        <Card className="flex min-w-0 flex-col gap-4 rounded-sheet p-6">
            <div className="flex items-start justify-between gap-3">
                <div>
                    <h2 className="text-admin-card-title font-bold text-ink">SEO to-do</h2>
                    <p className="text-admin-small text-ink-soft">{open.length > 0 ? 'Fix these first' : 'Nothing urgent'}</p>
                </div>
                {score !== null && <ScoreRing score={score} />}
            </div>
            {loading ? (
                <Skeleton className="h-24 w-full" />
            ) : !data?.audit ? (
                <p className="text-admin-body text-ink-soft">The first site audit runs at 6:00 tomorrow, or start one from the SEO overview.</p>
            ) : (
                <ul className="flex flex-col gap-3">
                    {shown.map((issue) => (
                        <li key={issue.key} className="flex items-baseline gap-3 text-admin-body text-ink">
                            <span aria-hidden="true" className={cn('size-2 shrink-0 translate-y-[-1px] rounded-full', TODO_DOT[issue.severity])} />
                            <span className="min-w-0">
                                <span className="sr-only">{issue.severity}: </span>
                                {issue.title}
                                {issue.count > 1 && <span className="whitespace-nowrap text-ink-soft"> · {issue.count}</span>}
                            </span>
                        </li>
                    ))}
                </ul>
            )}
            <Link to="/seo" className="inline-flex items-center gap-1 text-admin-body font-semibold text-royal hover:underline">
                Open SEO overview <ArrowRight className="size-4" aria-hidden="true" />
            </Link>
        </Card>
    );
}

function PublishingQueue() {
    const scheduled = usePages({ 'filter[status]': 'scheduled', sort: '-updated_at', per_page: 3 });
    const drafts = usePages({ 'filter[status]': 'draft', sort: '-updated_at', per_page: 3 });
    const items = [...(scheduled.data?.pages ?? []), ...(drafts.data?.pages ?? [])].slice(0, 4);

    return (
        <Card className="flex min-w-0 flex-col gap-4 rounded-sheet p-6">
            <h2 className="text-admin-card-title font-bold text-ink">Publishing queue</h2>
            {scheduled.isPending || drafts.isPending ? (
                <Skeleton className="h-24 w-full" />
            ) : items.length === 0 ? (
                <p className="text-admin-body text-ink-soft">Nothing scheduled and no drafts waiting.</p>
            ) : (
                <ul className="flex flex-col gap-4">
                    {items.map((page) => (
                        <li key={page.id} className="flex items-center justify-between gap-3">
                            <Link to={`/pages/${page.id}`} className="flex min-w-0 flex-col">
                                <span className="truncate text-admin-body font-semibold text-ink hover:text-royal">{page.title}</span>
                                <span className="truncate text-admin-small text-ink-soft">
                                    Page ·{' '}
                                    {page.status === 'scheduled' && page.scheduled_at
                                        ? `publishes ${new Date(page.scheduled_at).toLocaleString('en-US', { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' })}`
                                        : `edited ${receivedLabel(page.updated_at ?? new Date().toISOString()).toLowerCase()}`}
                                </span>
                            </Link>
                            <StatusBadge status={page.status} />
                        </li>
                    ))}
                </ul>
            )}
        </Card>
    );
}
