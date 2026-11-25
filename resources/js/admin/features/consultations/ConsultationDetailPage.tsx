import type { ReactNode } from 'react';
import { useState } from 'react';
import { ArrowLeft, Check, Ellipsis, Info, LockKeyhole, Mail, Phone, Siren, Trash2 } from 'lucide-react';
import { Link, useNavigate, useParams } from 'react-router';
import { ApiError } from '@shared/api/client';
import { can } from '@shared/auth/can';
import { Avatar, Badge, Button, ButtonLink, Card, ConfirmDialog, Dropdown, EmptyState, ErrorState, Radio, Select, Skeleton, Textarea, buttonClasses, cn, controlClasses, useToast, type DropdownItem } from '@shared/ui';
import { useCurrentUser } from '@admin/app/AuthProvider';
import { useAddNote, useAssignees, useConsultation, useDeleteConsultation, useUpdateConsultation } from './api';
import { STATUS_LABELS, receivedLabel, splitTitle, waitingLabel } from './format';
import type { ConsultationDetail, ConsultationQuiz, ConsultationStatus, QuizAnswerLine } from './types';

const STATUSES: ConsultationStatus[] = ['new', 'contacted', 'scheduled', 'closed', 'spam'];

/** The one next step the header offers for each status. */
const NEXT: Partial<Record<ConsultationStatus, { to: ConsultationStatus; label: string }>> = {
    new: { to: 'contacted', label: 'Mark as contacted' },
    contacted: { to: 'scheduled', label: 'Mark as scheduled' },
    scheduled: { to: 'closed', label: 'Close request' },
};

/**
 * Figma 05: one request, decrypted for the viewer. Opening this screen is
 * written to the audit log, so the data is fetched once and dropped on leave.
 */
export function ConsultationDetailPage() {
    const { id } = useParams();
    const requestId = Number(id);
    const consultation = useConsultation(requestId);

    if (consultation.isPending) {
        return (
            <div className="flex flex-col gap-6" aria-busy="true" aria-label="Loading request">
                <Skeleton className="h-4 w-56" />
                <Skeleton className="h-12 w-96" />
                <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
                    <Skeleton className="h-[420px] rounded-sheet" />
                    <Skeleton className="h-[420px] rounded-sheet" />
                </div>
            </div>
        );
    }

    if (consultation.isError) {
        const missing = consultation.error instanceof ApiError && consultation.error.code === 'NOT_FOUND';

        return missing ? (
            <EmptyState
                icon={<LockKeyhole className="size-5" aria-hidden="true" />}
                title="This request isn’t available"
                description="It may have been deleted, or it is assigned to someone else."
                action={
                    <ButtonLink to="/consultations" variant="secondary">
                        Back to requests
                    </ButtonLink>
                }
            />
        ) : (
            <ErrorState
                title="This request didn’t load"
                description={consultation.error instanceof ApiError ? consultation.error.humanMessage : 'Try again in a moment.'}
                requestId={consultation.error instanceof ApiError ? consultation.error.requestId : null}
                onRetry={() => void consultation.refetch()}
            />
        );
    }

    return <Detail request={consultation.data} />;
}

function Detail({ request }: { request: ConsultationDetail }) {
    const user = useCurrentUser();
    const navigate = useNavigate();
    const toast = useToast();
    const update = useUpdateConsultation(request.id);
    const remove = useDeleteConsultation();
    const [deleting, setDeleting] = useState(false);
    const canUpdate = can(user, 'consultations.update');
    const canAssign = canUpdate && can(user, 'consultations.view_all');
    const canDelete = can(user, 'consultations.export');
    const { title, accent } = splitTitle(request.topic.label);
    const next = NEXT[request.status];

    const setStatus = (to: ConsultationStatus) =>
        update.mutate(
            { status: to },
            {
                onSuccess: () => toast.success(`Marked ${STATUS_LABELS[to].toLowerCase()}`, request.reference),
                onError: (error) => toast.error('Status not changed', error instanceof ApiError ? error.humanMessage : undefined),
            },
        );

    const moreItems: DropdownItem[] = [
        ...(canUpdate && request.allowed_statuses.includes('spam') ? [{ label: 'Mark as spam', onSelect: () => setStatus('spam') }] : []),
        ...(canUpdate && request.status === 'spam' ? [{ label: 'Not spam', onSelect: () => setStatus('new') }] : []),
        ...(canUpdate && request.allowed_statuses.includes('closed') && next?.to !== 'closed' ? [{ label: 'Close request', onSelect: () => setStatus('closed') }] : []),
        ...(canDelete ? [{ label: 'Delete request', tone: 'danger' as const, icon: <Trash2 className="size-4" aria-hidden="true" />, onSelect: () => setDeleting(true) }] : []),
    ];

    return (
        <>
            <header className="flex w-full flex-col gap-3">
                <p className="flex items-center gap-2.5 font-display text-admin-overline font-bold tracking-[1.1px] text-ink-soft uppercase">
                    <span aria-hidden="true" className="h-[1.5px] w-7 bg-royal" />
                    <Link to="/consultations" className="hover:text-royal">
                        Consultations
                    </Link>
                    <span aria-hidden="true">/</span>
                    <span>{request.reference}</span>
                </p>
                <div className="flex flex-wrap items-end justify-between gap-x-8 gap-y-4">
                    <div className="flex min-w-0 flex-col gap-2">
                        <div className="flex flex-wrap items-center gap-3.5">
                            <h1 className="font-serif text-admin-hero text-ink">
                                {title} <em className="text-royal">{accent}</em>
                            </h1>
                            {request.is_overdue && (
                                <Badge tone="danger" dot>
                                    Overdue
                                </Badge>
                            )}
                            {!request.is_overdue && request.status !== 'new' && (
                                <Badge tone={request.status === 'scheduled' ? 'success' : request.status === 'contacted' ? 'warning' : 'neutral'} dot>
                                    {STATUS_LABELS[request.status]}
                                </Badge>
                            )}
                        </div>
                        <p className="text-[15px] leading-[1.5] text-ink-soft">{receivedLine(request)}</p>
                    </div>
                    <div className="flex flex-wrap items-center gap-2.5">
                        <ButtonLink to="/consultations" variant="ghost" icon={<ArrowLeft className="size-[18px]" aria-hidden="true" />}>
                            All requests
                        </ButtonLink>
                        {moreItems.length > 0 && (
                            <Dropdown
                                items={moreItems}
                                trigger={({ ref, ...props }) => (
                                    <button ref={ref} type="button" {...props} className={buttonClasses('secondary')}>
                                        <Ellipsis className="size-[18px]" aria-hidden="true" />
                                        More
                                    </button>
                                )}
                            />
                        )}
                        {canUpdate && next && request.allowed_statuses.includes(next.to) && (
                            <Button icon={<Check className="size-[18px]" aria-hidden="true" />} loading={update.isPending} onClick={() => setStatus(next.to)}>
                                {next.label}
                            </Button>
                        )}
                    </div>
                </div>
            </header>

            <div className="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
                <div className="flex min-w-0 flex-col gap-5">
                    <ContactCard request={request} />
                    {request.quiz && <QuizResultsCard quiz={request.quiz} />}
                    <NotesCard request={request} canAdd={canUpdate} />
                </div>
                <div className="flex min-w-0 flex-col gap-5">
                    <StatusCard request={request} canUpdate={canUpdate} canAssign={canAssign} />
                    <SourceCard request={request} />
                    <PrivacyCard request={request} canDelete={canDelete} canAudit={can(user, 'audit.view')} onDelete={() => setDeleting(true)} />
                    <ActivityCard request={request} />
                </div>
            </div>

            <ConfirmDialog
                open={deleting}
                title={`Delete ${request.reference}?`}
                description="The person’s details and every note are deleted for good. The audit log keeps only the reference and who deleted it."
                confirmLabel="Delete request"
                confirmWord="DELETE"
                busy={remove.isPending}
                error={remove.error instanceof ApiError ? remove.error.humanMessage : null}
                onClose={() => {
                    setDeleting(false);
                    remove.reset();
                }}
                onConfirm={() =>
                    remove.mutate(request.id, {
                        onSuccess: () => {
                            toast.success('Request deleted', request.reference);
                            void navigate('/consultations', { replace: true });
                        },
                    })
                }
            />
        </>
    );
}

/** "Received Monday, Oct 6 at 2:05 PM from the Contact page · waiting 2 days 1 hour" */
function receivedLine(request: ConsultationDetail): string {
    const received = new Date(request.received_at);
    const when = `${received.toLocaleDateString('en-US', { weekday: 'long', month: 'short', day: 'numeric' })} at ${received.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' })}`;
    const page = request.origin === 'quiz' ? 'the self-check' : request.source.page === '/contact' ? 'the Contact page' : request.source.page;
    const waiting = request.waiting_minutes !== null ? ` · waiting ${waitingLabel(request.waiting_minutes).replace(' d', ' days').replace(' h', ' hours').replace(' min', ' minutes').replace(/\b1 (day|hour|minute)s/g, '1 $1')}` : '';

    return `Received ${when}${page ? ` from ${page}` : ''}${waiting}`;
}

function CardTitle({ title, description, actions }: { title: string; description?: string; actions?: ReactNode }) {
    return (
        <div className="flex flex-wrap items-center justify-between gap-3">
            <div className="flex flex-col gap-0.5">
                <h2 className="text-admin-card-title font-bold text-ink">{title}</h2>
                {description && <p className="text-admin-small text-ink-soft">{description}</p>}
            </div>
            {actions}
        </div>
    );
}

function Fact({ label, children }: { label: string; children: ReactNode }) {
    return (
        <div className="flex min-w-0 flex-col gap-1">
            <dt className="font-display text-[10px] leading-[1.3] font-bold tracking-[1.1px] text-ink-soft uppercase">{label}</dt>
            <dd className="text-admin-body font-semibold break-words text-ink">{children}</dd>
        </div>
    );
}

function ContactCard({ request }: { request: ConsultationDetail }) {
    return (
        <Card className="flex flex-col gap-[22px] rounded-sheet p-7">
            <CardTitle
                title="Contact details"
                description="Decrypted for you · this view is logged"
                actions={
                    <div className="flex gap-2">
                        <a href={request.phone_href} className={buttonClasses('soft', 'sm')}>
                            <Phone className="size-4" aria-hidden="true" />
                            Call
                        </a>
                        {request.email && (
                            <a href={`mailto:${request.email}`} className={buttonClasses('secondary', 'sm')}>
                                <Mail className="size-4" aria-hidden="true" />
                                Email
                            </a>
                        )}
                    </div>
                }
            />
            <dl className="grid gap-x-6 gap-y-5 sm:grid-cols-2">
                <Fact label="Name">{request.name}</Fact>
                <Fact label="Phone">{request.phone}</Fact>
                <Fact label="Email">{request.email ?? <span className="font-normal text-ink-soft">Not given</span>}</Fact>
                <Fact label="Topic">{request.topic.label}</Fact>
                {request.insurance && <Fact label="Insurance">{request.insurance}</Fact>}
                {request.prior_treatment && <Fact label="Tried therapy / antidepressants">{request.prior_treatment.label}</Fact>}
                {request.contact_method && <Fact label="Preferred contact">{request.contact_method.label}</Fact>}
                <Fact label="Best time to call">{request.best_time?.label ?? <span className="font-normal text-ink-soft">Any time</span>}</Fact>
                {request.referred_by && <Fact label="Referred by">{request.referred_by}</Fact>}
            </dl>
            <div className="flex flex-col gap-2 rounded-[16px] bg-paper p-5">
                <p className="font-display text-[10px] leading-[1.3] font-bold tracking-[1.1px] text-ink-soft uppercase">Message</p>
                {request.message ? <p className="text-[15px] leading-[1.6] whitespace-pre-line text-ink">{request.message}</p> : <p className="text-admin-body text-ink-soft">No message — they asked for a call back.</p>}
            </div>
            <p className="flex items-start gap-2.5 rounded-control bg-warning-soft px-3.5 py-3 text-admin-small text-warning">
                <Info className="mt-px size-4 shrink-0" aria-hidden="true" />
                Keep clinical details in the EHR. Notes here are for scheduling and follow-up only.
            </p>
        </Card>
    );
}

/** The self-check behind a request: scores the server computed, then every answer. */
function QuizResultsCard({ quiz }: { quiz: ConsultationQuiz }) {
    return (
        <Card className="flex flex-col gap-[22px] rounded-sheet p-7">
            <CardTitle title="Self-check results" description="Screening scores computed by the server · not a diagnosis" />
            {quiz.crisis && (
                <div role="alert" className="flex items-start gap-3 rounded-[16px] bg-danger-soft px-4 py-3.5 text-danger">
                    <Siren className="mt-0.5 size-5 shrink-0" aria-hidden="true" />
                    <div className="flex flex-col gap-0.5">
                        <p className="font-semibold">Thoughts of self-harm reported</p>
                        <p className="text-admin-small">They answered the self-harm question above “Not at all” and were shown the 988 crisis line. Call them back first.</p>
                    </div>
                </div>
            )}
            {quiz.scales.length > 0 && (
                <div className="grid gap-4 sm:grid-cols-2">
                    {quiz.scales.map((scale) => (
                        <ScaleGauge key={scale.key} scale={scale} />
                    ))}
                </div>
            )}
            <AnswerList title="Answers" lines={quiz.answers} />
            {quiz.feedback && quiz.feedback.length > 0 && <AnswerList title="Feedback on the self-check" lines={quiz.feedback} />}
        </Card>
    );
}

function ScaleGauge({ scale }: { scale: ConsultationQuiz['scales'][number] }) {
    const percent = scale.max > 0 ? Math.min(100, Math.max(0, (scale.score / scale.max) * 100)) : 0;

    return (
        <div className="flex min-w-0 flex-col gap-3 rounded-[16px] bg-paper p-5">
            <div className="flex items-baseline justify-between gap-3">
                <p className="font-display text-[10px] leading-[1.3] font-bold tracking-[1.1px] text-ink-soft uppercase">
                    {scale.label}
                    {scale.instrument ? ` · ${scale.instrument}` : ''}
                </p>
                <p className="shrink-0 text-admin-small text-ink-soft">
                    <span className="font-display text-[22px] leading-none font-bold text-ink">{scale.score}</span> / {scale.max}
                </p>
            </div>
            <div
                role="meter"
                aria-label={`${scale.label} score`}
                aria-valuemin={0}
                aria-valuemax={scale.max}
                aria-valuenow={scale.score}
                className="relative h-2 rounded-full bg-[linear-gradient(90deg,var(--color-success),var(--color-warning),var(--color-danger))]"
            >
                <span aria-hidden="true" className="absolute top-1/2 size-4 -translate-x-1/2 -translate-y-1/2 rounded-full border-[3px] border-white bg-navy shadow" style={{ left: `${percent}%` }} />
            </div>
            <p className="text-admin-body font-semibold break-words text-ink">{scale.band}</p>
        </div>
    );
}

function AnswerList({ title, lines }: { title: string; lines: QuizAnswerLine[] }) {
    return (
        <div className="flex flex-col gap-2">
            <p className="font-display text-[10px] leading-[1.3] font-bold tracking-[1.1px] text-ink-soft uppercase">{title}</p>
            <dl className="flex flex-col divide-y divide-line rounded-[16px] border border-line">
                {lines.map((line, i) => (
                    <div key={i} className="grid gap-1 px-4 py-3 sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)] sm:gap-4">
                        <dt className="text-admin-small break-words text-ink-soft">{line.prompt.replace(/\*/g, '')}</dt>
                        <dd className="text-admin-body font-semibold break-words whitespace-pre-line text-ink">{line.answer || <span className="font-normal text-ink-soft">—</span>}</dd>
                    </div>
                ))}
            </dl>
        </div>
    );
}

function NotesCard({ request, canAdd }: { request: ConsultationDetail; canAdd: boolean }) {
    const toast = useToast();
    const add = useAddNote(request.id);
    const [body, setBody] = useState('');
    const error = add.error instanceof ApiError ? (add.error.fieldErrors.body?.[0] ?? add.error.humanMessage) : undefined;

    return (
        <Card className="flex flex-col gap-4 rounded-sheet p-7">
            <CardTitle title="Internal notes" description="Only staff can see these" />
            {canAdd && (
                <form
                    className="flex flex-col gap-3"
                    onSubmit={(event) => {
                        event.preventDefault();
                        add.mutate(body, {
                            onSuccess: () => {
                                setBody('');
                                toast.success('Note saved');
                            },
                        });
                    }}
                >
                    <Textarea
                        label="Add a note"
                        rows={2}
                        maxLength={1000}
                        value={body}
                        onChange={(event) => {
                            setBody(event.target.value);
                            if (add.isError) add.reset();
                        }}
                        placeholder="Left a voicemail at 10:15 AM. Will try again after 2 PM."
                        help="Scheduling and follow-up only"
                        error={error}
                    />
                    <div className="flex justify-end">
                        <Button type="submit" variant="navy" size="sm" loading={add.isPending} disabled={body.trim() === ''}>
                            Save note
                        </Button>
                    </div>
                </form>
            )}
            {request.notes.length === 0 ? (
                <p className="text-admin-body text-ink-soft">No notes yet.</p>
            ) : (
                <ul className="flex flex-col gap-4">
                    {request.notes.map((note) => (
                        <li key={note.id} className="flex gap-3">
                            <Avatar name={note.author?.name ?? '?'} />
                            <div className="flex min-w-0 flex-1 flex-col gap-1">
                                <p className="flex flex-wrap items-baseline gap-x-2">
                                    <span className="text-admin-body font-semibold text-ink">{note.author?.name ?? 'Former teammate'}</span>
                                    <span className="text-admin-small text-ink-soft">{receivedLabel(note.created_at)}</span>
                                </p>
                                <p className="text-admin-body whitespace-pre-line break-words text-ink">{note.body}</p>
                            </div>
                        </li>
                    ))}
                </ul>
            )}
        </Card>
    );
}

/** `datetime-local` speaks local wall time without a zone. */
function toLocalInput(iso: string | null): string {
    if (!iso) return '';
    const date = new Date(iso);
    const pad = (n: number) => String(n).padStart(2, '0');

    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

function StatusCard({ request, canUpdate, canAssign }: { request: ConsultationDetail; canUpdate: boolean; canAssign: boolean }) {
    const toast = useToast();
    const update = useUpdateConsultation(request.id);
    const [followUp, setFollowUp] = useState(toLocalInput(request.follow_up_at));
    const onError = (error: Error) => toast.error('Not saved', error instanceof ApiError ? error.humanMessage : undefined);

    return (
        <Card className="flex flex-col gap-3.5 rounded-sheet p-6">
            <CardTitle title="Status" />
            <fieldset className="flex flex-col gap-2.5" disabled={!canUpdate || update.isPending}>
                <legend className="sr-only">Status</legend>
                {STATUSES.map((status) => (
                    <Radio
                        key={status}
                        name="status"
                        label={STATUS_LABELS[status]}
                        checked={request.status === status}
                        disabled={request.status !== status && !request.allowed_statuses.includes(status)}
                        onChange={() => update.mutate({ status }, { onSuccess: () => toast.success(`Marked ${STATUS_LABELS[status].toLowerCase()}`), onError })}
                    />
                ))}
            </fieldset>
            {canAssign ? (
                <AssigneeSelect request={request} disabled={update.isPending} onChange={(value) => update.mutate({ assigned_to: value }, { onSuccess: () => toast.success(value === null ? 'Unassigned' : 'Assigned'), onError })} />
            ) : (
                <div className="flex flex-col gap-1.5">
                    <p className="text-admin-label font-semibold text-ink">Assigned to</p>
                    <p className="text-admin-body text-ink">{request.assigned_to?.name ?? 'Unassigned'}</p>
                </div>
            )}
            <div className="flex flex-col gap-1.5">
                <label htmlFor={`follow-up-${request.id}`} className="text-admin-label font-semibold text-ink">
                    Follow-up by
                </label>
                <input
                    id={`follow-up-${request.id}`}
                    type="datetime-local"
                    value={followUp}
                    disabled={!canUpdate || update.isPending}
                    aria-invalid={request.is_overdue || undefined}
                    aria-describedby={request.is_overdue ? `follow-up-${request.id}-help` : undefined}
                    onChange={(event) => setFollowUp(event.target.value)}
                    onBlur={() => {
                        if (followUp === '' || followUp === toLocalInput(request.follow_up_at)) return;
                        update.mutate({ follow_up_at: new Date(followUp).toISOString() }, { onSuccess: () => toast.success('Follow-up time saved'), onError });
                    }}
                    className={controlClasses(request.is_overdue)}
                />
                {request.is_overdue && (
                    <p id={`follow-up-${request.id}-help`} className="text-admin-small text-danger">
                        Past the 1-business-day target
                    </p>
                )}
            </div>
        </Card>
    );
}

function AssigneeSelect({ request, disabled, onChange }: { request: ConsultationDetail; disabled: boolean; onChange: (value: number | null) => void }) {
    const staff = useAssignees();
    const options = staff.data ?? (request.assigned_to ? [request.assigned_to] : []);

    return (
        <Select
            label="Assigned to"
            value={request.assigned_to?.id ?? ''}
            disabled={disabled || staff.isPending}
            onChange={(event) => onChange(event.target.value === '' ? null : Number(event.target.value))}
        >
            <option value="">Unassigned</option>
            {options.map((person) => (
                <option key={person.id} value={person.id}>
                    {person.name}
                </option>
            ))}
        </Select>
    );
}

function SourceCard({ request }: { request: ConsultationDetail }) {
    const rows: [string, string | null][] = [
        ['Page', request.source.page],
        ['First visit', request.source.first_touch],
        ['Previous page', request.source.previous_page],
        ['Device', request.source.device],
    ];

    return (
        <Card className="flex flex-col gap-3.5 rounded-sheet p-6">
            <CardTitle title="Where it came from" />
            <dl className="flex flex-col gap-3.5">
                {rows
                    .filter((row): row is [string, string] => row[1] !== null && row[1] !== '')
                    .map(([label, value]) => (
                        <div key={label} className="flex items-start justify-between gap-4 text-admin-body">
                            <dt className="shrink-0 text-ink-soft">{label}</dt>
                            <dd className="min-w-0 text-right font-semibold break-words text-ink">{value}</dd>
                        </div>
                    ))}
            </dl>
        </Card>
    );
}

function PrivacyCard({ request, canDelete, canAudit, onDelete }: { request: ConsultationDetail; canDelete: boolean; canAudit: boolean; onDelete: () => void }) {
    const opened = `Opened ${request.privacy.view_count} ${request.privacy.view_count === 1 ? 'time' : 'times'}`;
    const deletes = new Date(`${request.privacy.deletes_on}T12:00:00`).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });

    return (
        <Card className="flex flex-col gap-3 rounded-sheet p-6">
            <CardTitle title="Privacy & retention" />
            <ul className="flex flex-col gap-3 text-admin-body text-ink">
                <PrivacyLine>Encrypted at rest (AES-256)</PrivacyLine>
                <PrivacyLine>
                    {canAudit ? (
                        <>
                            {opened} ·{' '}
                            <Link to={`/audit-log?filter[auditable_type]=consultation_request&filter[auditable_id]=${request.id}`} className="font-semibold text-royal hover:underline">
                                see audit log
                            </Link>
                        </>
                    ) : (
                        opened
                    )}
                </PrivacyLine>
                <PrivacyLine>Auto-deletes {deletes}</PrivacyLine>
            </ul>
            {canDelete && (
                <Button variant="danger" size="sm" className="self-start" icon={<Trash2 className="size-4" aria-hidden="true" />} onClick={onDelete}>
                    Delete request
                </Button>
            )}
        </Card>
    );
}

function PrivacyLine({ children }: { children: ReactNode }) {
    return (
        <li className="flex items-center gap-2.5">
            <LockKeyhole className="size-4 shrink-0 text-royal" aria-hidden="true" />
            <span>{children}</span>
        </li>
    );
}

function ActivityCard({ request }: { request: ConsultationDetail }) {
    return (
        <Card className="flex flex-col rounded-sheet p-6">
            <div className="pb-3">
                <CardTitle title="Activity" />
            </div>
            <ol className="flex flex-col">
                {request.activity.map((entry, index) => (
                    <li key={entry.id} className="flex gap-3 py-2">
                        <span aria-hidden="true" className={cn('mt-[7px] size-2 shrink-0 rounded-full', index === request.activity.length - 1 ? 'bg-royal' : 'bg-ink-soft')} />
                        <div className="flex min-w-0 flex-col">
                            <span className="text-admin-body text-ink">{entry.label}</span>
                            <span className="text-admin-small text-ink-soft">{receivedLabel(entry.created_at)}</span>
                        </div>
                    </li>
                ))}
            </ol>
        </Card>
    );
}
