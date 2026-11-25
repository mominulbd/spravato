import { useState } from 'react';
import { ChevronDown, Download, RefreshCw } from 'lucide-react';
import { Link } from 'react-router';
import { ApiError } from '@shared/api/client';
import { can } from '@shared/auth/can';
import { Badge, Button, Card, ErrorState, Skeleton, cn, useToast, type BadgeTone } from '@shared/ui';
import { useCurrentUser } from '@admin/app/AuthProvider';
import { PageHeader } from '@admin/components/PageHeader';
import { useRunSeoAudit, useSeoAudit } from './api';
import { auditHeadline, checkedAt, vitalValue } from './format';
import type { AuditIssue, IssueSeverity, SeoAuditOverview, VitalMetric } from './types';

const SEVERITY: Record<IssueSeverity, { label: string; tone: BadgeTone }> = {
    urgent: { label: 'Urgent', tone: 'danger' },
    warning: { label: 'Warning', tone: 'warning' },
    notice: { label: 'Notice', tone: 'info' },
    passed: { label: 'Passed', tone: 'success' },
};

const RATING: Record<VitalMetric['rating'], { bar: string; text: string }> = {
    good: { bar: 'bg-success', text: 'text-success' },
    needs_improvement: { bar: 'bg-warning', text: 'text-warning' },
    poor: { bar: 'bg-danger', text: 'text-danger' },
};

/** Figma 19: the nightly site audit, Search Console and Core Web Vitals in one place. */
export function SeoOverviewPage() {
    const overview = useSeoAudit();
    // Anyone with SEO access reads the audit; running one on demand is seo.manage.
    const canRun = can(useCurrentUser(), 'seo.manage');
    const run = useRunSeoAudit();
    const toast = useToast();
    const data = overview.data;
    const running = run.isPending || Boolean(data?.running);

    return (
        <>
            <PageHeader
                trail={['SEO', 'Overview']}
                title="Search"
                accent="health."
                description={`A daily check of every page, image and link on ${data?.site_host || 'the website'}, plus data from Google Search Console.`}
                actions={
                    <>
                        {canRun && (
                            <Button
                                variant="secondary"
                                icon={<RefreshCw className={cn('size-[18px]', running && 'animate-spin motion-reduce:animate-none')} aria-hidden="true" />}
                                disabled={running}
                                onClick={() => run.mutate(undefined, { onError: (error) => toast.error('The audit didn’t start', error instanceof ApiError ? error.humanMessage : undefined) })}
                            >
                                {running ? 'Checking…' : 'Run audit now'}
                            </Button>
                        )}
                        <Button icon={<Download className="size-[18px]" aria-hidden="true" />} disabled={!data?.audit} onClick={() => window.location.assign('/api/v1/admin/seo-audit/export')}>
                            Export report
                        </Button>
                    </>
                }
            />
            {overview.isPending && <Skeleton className="h-[214px] w-full rounded-sheet" />}
            {overview.isError && <ErrorState title="The SEO overview didn’t load" description={overview.error instanceof ApiError ? overview.error.humanMessage : 'Try again in a moment.'} onRetry={() => void overview.refetch()} />}
            {data && (
                <>
                    <Hero data={data} running={running} canRun={canRun} />
                    {data.audit && (
                        <div className="grid items-start gap-5 xl:grid-cols-[minmax(0,1fr)_380px]">
                            <Issues data={data} />
                            <div className="flex min-w-0 flex-col gap-5">
                                <TopSearches data={data} />
                                <Vitals data={data} />
                            </div>
                        </div>
                    )}
                </>
            )}
        </>
    );
}

function Hero({ data, running, canRun }: { data: SeoAuditOverview; running: boolean; canRun: boolean }) {
    const audit = data.audit;

    if (!audit || audit.score === null) {
        return (
            <section aria-labelledby="health-title" className="flex flex-col gap-3 rounded-sheet bg-navy p-8 text-white lg:p-10">
                <p className="font-display text-admin-overline font-bold tracking-[1.1px] text-white/70 uppercase">Site health</p>
                <h2 id="health-title" className="font-serif text-[30px] leading-[1.15] tracking-[-0.02em] md:text-[34px]">
                    {running ? 'Checking every page now' : 'No audit has run yet'} <em className="text-sky">{running ? '— this takes a minute.' : canRun ? '— run one to see your score.' : '— the first one runs at 6:00.'}</em>
                </h2>
                <p className="max-w-[560px] text-[15px] leading-[1.6] text-white/80">The audit runs by itself every morning at 6:00. It reads every published page, post and condition, as Google would.</p>
            </section>
        );
    }

    const fixes = data.issues.filter((issue) => issue.severity === 'urgent' || issue.severity === 'warning').length;
    const headline = auditHeadline(data.site_host, audit.score, fixes);
    const stats = [
        audit.indexed !== null ? { label: 'Indexed', value: `${audit.indexed} of ${audit.indexable}` } : { label: 'Open to search', value: `${audit.indexable} of ${audit.urls}` },
        { label: 'Clicks · 28 d', value: data.search ? data.search.clicks.toLocaleString('en-US') : '—' },
        { label: 'Avg. position', value: data.search?.position != null ? data.search.position.toFixed(1) : '—' },
        { label: 'Core Web Vitals', value: data.vitals ? (data.vitals.passing ? 'Passing' : 'Needs work') : '—' },
    ];

    return (
        <section aria-labelledby="health-title" className="flex flex-col gap-8 rounded-sheet bg-navy p-8 text-white sm:flex-row sm:items-center lg:gap-10 lg:p-10">
            <HealthRing score={audit.score} />
            <div className="flex min-w-0 flex-col gap-4">
                <p className="font-display text-admin-overline font-bold tracking-[1.1px] text-white/70 uppercase">
                    Site health · {running ? 'checking now…' : `checked ${checkedAt(audit.started_at)}`}
                </p>
                <h2 id="health-title" className="font-serif text-[28px] leading-[1.15] tracking-[-0.02em] md:text-[32px]">
                    {headline.lead} — <em className="text-sky">{headline.accent}</em>
                </h2>
                <dl className="flex flex-wrap gap-x-8 gap-y-4">
                    {stats.map((stat) => (
                        <div key={stat.label} className="flex flex-col gap-0.5">
                            <dt className="text-admin-small text-white/70">{stat.label}</dt>
                            <dd className="text-[18px] leading-[1.3] font-bold">{stat.value}</dd>
                        </div>
                    ))}
                </dl>
            </div>
        </section>
    );
}

/** The large score of Figma 19's hero: a light arc on navy. */
function HealthRing({ score }: { score: number }) {
    const size = 150;
    const radius = (size - 8) / 2;
    const circumference = 2 * Math.PI * radius;

    return (
        <div className="relative grid size-[150px] shrink-0 place-items-center" role="img" aria-label={`Site health ${score} out of 100`}>
            <svg width={size} height={size} viewBox={`0 0 ${size} ${size}`} className="absolute inset-0 -rotate-90" aria-hidden="true">
                <circle cx={size / 2} cy={size / 2} r={radius} fill="none" strokeWidth="6" className="stroke-white/15" />
                <circle cx={size / 2} cy={size / 2} r={radius} fill="none" strokeWidth="6" strokeLinecap="round" className="stroke-sky" strokeDasharray={circumference} strokeDashoffset={circumference * (1 - score / 100)} />
            </svg>
            <span className="flex flex-col items-center gap-1" aria-hidden="true">
                <span className="font-serif text-[48px] leading-none">{score}</span>
                <span className="font-display text-[10px] font-bold tracking-[1.1px] text-white/70 uppercase">Out of 100</span>
            </span>
        </div>
    );
}

function Issues({ data }: { data: SeoAuditOverview }) {
    return (
        <section aria-labelledby="issues-title" className="flex min-w-0 flex-col gap-3">
            <div className="flex flex-wrap items-baseline justify-between gap-2">
                <h2 id="issues-title" className="font-serif text-[24px] leading-[1.2] text-ink">
                    Issues
                </h2>
                <p className="text-admin-small text-ink-soft">
                    {data.open} open · {data.fixed_this_week} fixed this week
                </p>
            </div>
            <Card className="overflow-hidden rounded-sheet">
                <div className="hidden grid-cols-[120px_minmax(0,1fr)_56px_112px] gap-4 border-b border-line px-6 py-4 font-display text-admin-overline font-bold tracking-[1.1px] text-ink-soft uppercase md:grid" aria-hidden="true">
                    <span>Severity</span>
                    <span>Issue</span>
                    <span className="text-right">Pages</span>
                    <span />
                </div>
                <ul aria-label="Audit findings">
                    {data.issues.map((issue) => (
                        <IssueRow key={issue.key} issue={issue} />
                    ))}
                </ul>
            </Card>
        </section>
    );
}

function IssueRow({ issue }: { issue: AuditIssue }) {
    const [open, setOpen] = useState(false);
    const severity = SEVERITY[issue.severity];
    const single = issue.items.length === 1 ? issue.items[0] : null;
    const verb = issue.severity === 'notice' ? 'View' : 'Fix';
    const listId = `issue-${issue.key}-pages`;
    const pill = 'inline-flex h-9 items-center gap-1 rounded-full bg-sky-soft px-4 font-display text-[14px] font-bold text-royal transition-colors hover:bg-sky';

    return (
        <li className="border-b border-line last:border-b-0">
            <div className="grid grid-cols-[minmax(0,1fr)_auto] items-center gap-x-4 gap-y-2 px-6 py-4 md:grid-cols-[120px_minmax(0,1fr)_56px_112px]">
                <span className="col-span-2 md:col-span-1">
                    <Badge tone={severity.tone} dot>
                        {severity.label}
                    </Badge>
                </span>
                <span className="flex min-w-0 flex-col gap-0.5">
                    <span className="text-admin-body font-semibold text-ink">{issue.title}</span>
                    <span className="text-admin-small break-words text-ink-soft">{issue.detail}</span>
                </span>
                <span className="hidden text-right text-admin-body text-ink md:block">{issue.severity === 'passed' ? '—' : issue.count}</span>
                <span className="flex justify-end">
                    {issue.severity === 'passed' ? null : issue.action ? (
                        <Link to={issue.action.to} className={pill}>
                            {issue.action.label}
                        </Link>
                    ) : single?.admin_path ? (
                        <Link to={single.admin_path} className={pill}>
                            {verb}
                        </Link>
                    ) : (
                        <button type="button" className={pill} aria-expanded={open} aria-controls={listId} onClick={() => setOpen(!open)}>
                            {verb}
                            <ChevronDown className={cn('size-4 transition-transform', open && 'rotate-180')} aria-hidden="true" />
                        </button>
                    )}
                </span>
            </div>
            {open && (
                <ul id={listId} className="flex flex-col gap-1 bg-paper px-6 py-3 md:pl-[160px]">
                    {issue.items.map((item) => (
                        <li key={item.key} className="flex min-w-0 items-baseline justify-between gap-3 py-1">
                            {item.admin_path ? (
                                <Link to={item.admin_path} className="min-w-0 truncate text-admin-body font-semibold text-royal hover:underline">
                                    {item.label}
                                </Link>
                            ) : (
                                <span className="min-w-0 truncate text-admin-body text-ink">{item.label}</span>
                            )}
                            {item.path && item.path !== item.label && <span className="shrink-0 text-admin-small text-ink-soft">{item.path}</span>}
                        </li>
                    ))}
                </ul>
            )}
        </li>
    );
}

function TopSearches({ data }: { data: SeoAuditOverview }) {
    const queries = data.search?.queries ?? [];

    return (
        <Card className="flex min-w-0 flex-col gap-4 rounded-sheet p-6">
            <div className="flex items-start justify-between gap-3">
                <div>
                    <h2 className="text-admin-card-title font-bold text-ink">Top searches</h2>
                    <p className="text-admin-small text-ink-soft">Google · last 28 days</p>
                </div>
                <Badge>GSC</Badge>
            </div>
            {!data.connections.search_console ? (
                <p className="text-admin-body text-ink-soft">Connect Google Search Console to see what people search for before they find the clinic. Your developer adds the service account in the server settings.</p>
            ) : queries.length === 0 ? (
                <p className="text-admin-body text-ink-soft">Google hasn’t reported any searches yet. New sites take a few weeks.</p>
            ) : (
                <ol className="flex flex-col gap-3">
                    {queries.map((row) => (
                        <li key={row.query} className="flex items-center justify-between gap-3">
                            <span className="min-w-0 truncate text-admin-body text-ink">{row.query}</span>
                            <span className="flex shrink-0 items-center gap-3">
                                <span className="text-admin-small text-ink-soft">{row.clicks.toLocaleString('en-US')} clicks</span>
                                <span className="rounded-full bg-sky-soft px-2 py-0.5 text-admin-small font-semibold text-royal" title="Average position in Google">
                                    #{row.position.toFixed(1)}
                                </span>
                            </span>
                        </li>
                    ))}
                </ol>
            )}
        </Card>
    );
}

function Vitals({ data }: { data: SeoAuditOverview }) {
    const vitals = data.vitals;

    return (
        <Card className="flex min-w-0 flex-col gap-4 rounded-sheet p-6">
            <div className="flex items-start justify-between gap-3">
                <div>
                    <h2 className="text-admin-card-title font-bold text-ink">Core Web Vitals</h2>
                    <p className="text-admin-small text-ink-soft">Real visitors · mobile</p>
                </div>
                {vitals && (
                    <Badge tone={vitals.passing ? 'success' : 'warning'} dot>
                        {vitals.passing ? 'Passing' : 'Needs work'}
                    </Badge>
                )}
            </div>
            {!vitals ? (
                <p className="text-admin-body text-ink-soft">
                    {data.connections.core_web_vitals ? 'Google doesn’t have enough visits from Chrome yet to measure real speed. This fills in as traffic grows.' : 'Add a Chrome UX Report API key to see how fast the site feels to real visitors.'}
                </p>
            ) : (
                <ul className="flex flex-col gap-4">
                    {vitals.metrics.map((metric) => (
                        <li key={metric.key} className="flex flex-col gap-2">
                            <span className="flex items-baseline justify-between gap-3">
                                <span className="text-admin-body text-ink">{metric.label}</span>
                                <span className={cn('text-admin-body font-semibold', RATING[metric.rating].text)}>{vitalValue(metric)}</span>
                            </span>
                            <span className="h-1.5 overflow-hidden rounded-full bg-paper" aria-hidden="true">
                                <span className={cn('block h-full rounded-full', RATING[metric.rating].bar)} style={{ width: `${Math.max(4, Math.min(100, (metric.value / metric.poor) * 100))}%` }} />
                            </span>
                        </li>
                    ))}
                </ul>
            )}
        </Card>
    );
}
