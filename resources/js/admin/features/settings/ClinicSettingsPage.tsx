import { useEffect, useState } from 'react';
import { useBlocker, useSearchParams } from 'react-router';
import { Check, DatabaseZap, ExternalLink, Globe, Map as MapIcon, Megaphone, Plus, ShieldCheck, Trash2, TriangleAlert } from 'lucide-react';
import { ApiError } from '@shared/api/client';
import { Badge, Button, ConfirmDialog, ErrorState, Select, Skeleton, TagInput, TextInput, Textarea, Toggle, cn, useToast } from '@shared/ui';
import { PageHeader } from '@admin/components/PageHeader';
import { useClearCache, useClinicSettings, useSaveSettings, type CacheScope } from './api';
import { dirtyGroups, flattenErrors, localErrors } from './form';
import { ClosuresEditor, WeekEditor } from './HoursEditor';
import { SettingsCard } from './SettingsCard';
import { SOCIAL_NETWORKS, type ClinicSettings, type SettingsGroup } from './types';

const TABS = [
    { value: 'profile', label: 'Clinic profile' },
    { value: 'hours', label: 'Hours' },
    { value: 'announcement', label: 'Announcement bar' },
    { value: 'disclaimers', label: 'Disclaimers' },
    { value: 'privacy', label: 'Privacy & retention' },
    { value: 'security', label: 'Security' },
    { value: 'cache', label: 'Cache' },
] as const;

type Tab = (typeof TABS)[number]['value'];

/** Which settings groups each tab shows (the profile tab also edits hours and the crisis disclaimer). */
const TAB_GROUPS: Record<Tab, SettingsGroup[]> = {
    profile: ['profile', 'hours', 'disclaimers'],
    hours: ['hours'],
    announcement: ['announcement'],
    disclaimers: ['disclaimers'],
    privacy: ['privacy'],
    security: ['security'],
    cache: [],
};

/** Figma 24. Edits are kept across tabs; "Save changes" saves every changed group. */
export function ClinicSettingsPage() {
    const settings = useClinicSettings();

    return (
        <>
            {settings.isPending ? (
                <div className="flex flex-col gap-4" aria-busy="true">
                    <Skeleton className="h-4 w-48" />
                    <Skeleton className="h-10 w-80" />
                    <Skeleton className="h-96 w-full rounded-sheet" />
                </div>
            ) : settings.isError ? (
                <ErrorState title="Settings didn’t load" description={settings.error instanceof ApiError ? settings.error.humanMessage : 'Try again in a moment.'} onRetry={() => void settings.refetch()} />
            ) : (
                <SettingsForm saved={settings.data} />
            )}
        </>
    );
}

function SettingsForm({ saved }: { saved: ClinicSettings }) {
    const toast = useToast();
    const save = useSaveSettings();
    const [params, setParams] = useSearchParams();
    const tab = (TABS.some((item) => item.value === params.get('tab')) ? params.get('tab') : 'profile') as Tab;
    const [draft, setDraft] = useState<ClinicSettings>(saved);
    const [errors, setErrors] = useState<Partial<Record<SettingsGroup, Record<string, string>>>>({});
    const [saving, setSaving] = useState(false);
    const dirty = dirtyGroups(draft, saved);

    const blocker = useBlocker(({ currentLocation, nextLocation }) => dirty.length > 0 && currentLocation.pathname !== nextLocation.pathname);

    useEffect(() => {
        if (dirty.length === 0) return;
        const warn = (event: BeforeUnloadEvent) => event.preventDefault();
        window.addEventListener('beforeunload', warn);

        return () => window.removeEventListener('beforeunload', warn);
    }, [dirty.length]);

    const set = <G extends keyof ClinicSettings>(group: G, value: ClinicSettings[G]) => setDraft((current) => ({ ...current, [group]: value }));
    const patch = <G extends 'profile' | 'announcement' | 'disclaimers' | 'privacy' | 'security'>(group: G, value: Partial<ClinicSettings[G]>) =>
        setDraft((current) => ({ ...current, [group]: { ...current[group], ...value } }));
    const err = (group: SettingsGroup) => errors[group] ?? {};

    const submit = async () => {
        const found: Partial<Record<SettingsGroup, Record<string, string>>> = {};

        for (const group of dirty) {
            const local = localErrors(group, draft);
            if (Object.keys(local).length > 0) found[group] = local;
        }

        if (Object.keys(found).length > 0) {
            setErrors(found);
            focusTab(found);
            return;
        }

        setSaving(true);
        const done: SettingsGroup[] = [];

        for (const group of dirty) {
            try {
                await save.mutateAsync({ group, draft });
                done.push(group);
            } catch (error) {
                if (error instanceof ApiError && error.status === 422) {
                    found[group] = flattenErrors(error.fieldErrors);
                } else {
                    toast.error('Settings weren’t saved', error instanceof ApiError ? error.humanMessage : undefined);
                    break;
                }
            }
        }

        setSaving(false);
        setErrors(found);

        if (Object.keys(found).length > 0) {
            focusTab(found);
        } else if (done.length > 0) {
            toast.success('Settings saved', 'The website updates within seconds.');
        }
    };

    const focusTab = (found: Partial<Record<SettingsGroup, Record<string, string>>>) => {
        if (TAB_GROUPS[tab].some((group) => found[group])) return;
        const target = TABS.find((item) => TAB_GROUPS[item.value].some((group) => found[group]));
        if (target) setParams({ tab: target.value }, { replace: true });
    };

    const tabDirty = (value: Tab) => TAB_GROUPS[value].some((group) => dirty.includes(group));
    const { profile, announcement, disclaimers, privacy, security, hours } = draft;

    return (
        <>
            <PageHeader
                trail={['System', 'Clinic settings']}
                title="Clinic"
                accent="settings."
                description="Details used across the website header, footer, contact page, schema and Google Business Profile."
                actions={
                    <>
                        {dirty.length > 0 && <Badge tone="warning">Unsaved changes</Badge>}
                        <Button icon={<Check className="size-4" />} loading={saving} disabled={dirty.length === 0} onClick={() => void submit()}>
                            Save changes
                        </Button>
                    </>
                }
            />

            <div role="tablist" aria-label="Settings sections" className="flex w-full gap-7 overflow-x-auto border-b border-line">
                {TABS.map((item) => (
                    <button
                        key={item.value}
                        type="button"
                        role="tab"
                        aria-selected={tab === item.value}
                        onClick={() => setParams(item.value === 'profile' ? {} : { tab: item.value }, { replace: true })}
                        className={cn(
                            '-mb-px flex shrink-0 items-center gap-2 border-b-2 px-0.5 pt-2.5 pb-3 text-admin-body font-semibold',
                            tab === item.value ? 'border-royal text-royal' : 'border-transparent text-ink-soft hover:text-ink',
                        )}
                    >
                        {item.label}
                        {tabDirty(item.value) && <span aria-label="unsaved" className="size-1.5 rounded-full bg-warning" />}
                    </button>
                ))}
            </div>

            {tab === 'profile' && (
                <div className="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_400px]">
                    <div className="flex min-w-0 flex-col gap-6">
                        <SettingsCard title="Name, address & phone" description="Keep these identical everywhere — Google compares them.">
                            <TextInput label="Clinic name" value={profile.name} maxLength={120} error={err('profile').name} onChange={(event) => patch('profile', { name: event.target.value })} />
                            <TextInput label="Tagline" value={profile.tagline} maxLength={200} error={err('profile').tagline} help="Shown under the logo in the footer." onChange={(event) => patch('profile', { tagline: event.target.value })} />
                            <div className="grid gap-4 md:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
                                <TextInput label="Street address" value={profile.street} maxLength={120} error={err('profile').street} onChange={(event) => patch('profile', { street: event.target.value })} />
                                <TextInput label="City" value={profile.city} maxLength={80} error={err('profile').city} onChange={(event) => patch('profile', { city: event.target.value })} />
                            </div>
                            <div className="grid gap-4 md:grid-cols-[minmax(0,0.8fr)_minmax(0,0.8fr)_minmax(0,1.2fr)]">
                                <TextInput label="State" value={profile.state} maxLength={2} error={err('profile').state} onChange={(event) => patch('profile', { state: event.target.value.toUpperCase() })} />
                                <TextInput label="ZIP" value={profile.zip} maxLength={10} inputMode="numeric" error={err('profile').zip} onChange={(event) => patch('profile', { zip: event.target.value })} />
                                <TextInput label="Phone" value={profile.phone} maxLength={20} type="tel" error={err('profile').phone} onChange={(event) => patch('profile', { phone: event.target.value })} />
                            </div>
                            <TextInput label="Email" value={profile.email} type="email" maxLength={190} error={err('profile').email} onChange={(event) => patch('profile', { email: event.target.value })} />
                            <MapTile latitude={profile.latitude} longitude={profile.longitude} />
                            <div className="grid gap-4 md:grid-cols-2">
                                <TextInput label="Latitude" value={profile.latitude ?? ''} inputMode="decimal" error={err('profile').latitude} onChange={(event) => patch('profile', { latitude: event.target.value })} />
                                <TextInput label="Longitude" value={profile.longitude ?? ''} inputMode="decimal" error={err('profile').longitude} onChange={(event) => patch('profile', { longitude: event.target.value })} />
                            </div>
                        </SettingsCard>

                        <SettingsCard title="Service area" description="Shown as “serving Greater Tacoma” and in schema areaServed.">
                            <TextInput label="Service area line" value={profile.service_area_label} maxLength={80} error={err('profile').service_area_label} help="Shown in the header utility bar." onChange={(event) => patch('profile', { service_area_label: event.target.value })} />
                            <TagInput label="Cities served" value={profile.service_area} max={12} maxLength={40} placeholder="Add a city" error={firstWithPrefix(err('profile'), 'service_area')} onChange={(value) => patch('profile', { service_area: value })} />
                        </SettingsCard>

                        <SettingsCard title="Online presence" description="Links in the footer and the clinic’s schema sameAs.">
                            <TextInput label="Main clinic website" type="url" placeholder="https://" value={profile.main_site_url} error={err('profile').main_site_url} help="The clinic’s main website address." onChange={(event) => patch('profile', { main_site_url: event.target.value })} />
                            <div className="flex flex-col gap-3">
                                <span className="text-admin-label font-semibold text-ink">Social profiles</span>
                                {profile.social.length === 0 && <p className="text-admin-small text-ink-soft">None yet.</p>}
                                {profile.social.map((link, index) => (
                                    <div key={index} className="grid grid-cols-[150px_minmax(0,1fr)_auto] items-start gap-3">
                                        <Select label={<span className="sr-only">Network</span>} value={link.network} onChange={(event) => patch('profile', { social: profile.social.map((item, i) => (i === index ? { ...item, network: event.target.value as typeof link.network } : item)) })}>
                                            {SOCIAL_NETWORKS.map((network) => (
                                                <option key={network.value} value={network.value}>
                                                    {network.label}
                                                </option>
                                            ))}
                                        </Select>
                                        <TextInput label={<span className="sr-only">Profile URL</span>} type="url" placeholder="https://" value={link.url} error={err('profile')[`social.${index}.url`]} onChange={(event) => patch('profile', { social: profile.social.map((item, i) => (i === index ? { ...item, url: event.target.value } : item)) })} />
                                        <button type="button" aria-label={`Remove ${link.network} link`} onClick={() => patch('profile', { social: profile.social.filter((_, i) => i !== index) })} className="mt-1 rounded-full p-2.5 text-ink-soft hover:bg-danger-soft hover:text-danger">
                                            <Trash2 className="size-4" aria-hidden="true" />
                                        </button>
                                    </div>
                                ))}
                                <Button variant="soft" size="sm" className="self-start" icon={<Plus className="size-4" />} disabled={profile.social.length >= 6} onClick={() => patch('profile', { social: [...profile.social, { network: 'facebook', url: '' }] })}>
                                    Add a profile
                                </Button>
                            </div>
                        </SettingsCard>
                    </div>

                    <div className="flex flex-col gap-6">
                        <SettingsCard title="Office hours" description="Used in the header, footer and schema.">
                            <WeekEditor compact hours={hours} errors={err('hours')} onChange={(value) => set('hours', value)} />
                            <div className="flex flex-wrap gap-x-5 gap-y-2">
                                <button type="button" onClick={() => setParams({ tab: 'hours' }, { replace: true })} className="text-admin-body font-semibold text-royal hover:underline">
                                    Edit times
                                </button>
                                <button type="button" onClick={() => setParams({ tab: 'hours' }, { replace: true })} className="text-admin-body font-semibold text-royal hover:underline">
                                    Add a holiday closure →
                                </button>
                            </div>
                        </SettingsCard>
                        <CrisisCard value={disclaimers.crisis} error={err('disclaimers').crisis} onChange={(value) => patch('disclaimers', { crisis: value })} />
                    </div>
                </div>
            )}

            {tab === 'hours' && (
                <div className="grid items-start gap-6 xl:grid-cols-2">
                    <SettingsCard title="Weekly hours" description="Turn a day off to show it as Closed.">
                        <WeekEditor hours={hours} errors={err('hours')} onChange={(value) => set('hours', value)} />
                    </SettingsCard>
                    <SettingsCard title="Holiday closures" description="One-off days the clinic is closed. Past dates drop off the website automatically.">
                        <ClosuresEditor closures={hours.closures} errors={err('hours')} onChange={(closures) => set('hours', { ...hours, closures })} />
                    </SettingsCard>
                </div>
            )}

            {tab === 'announcement' && (
                <div className="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
                    <SettingsCard title="Announcement bar" description="A short notice above the header on every page — holiday hours, a new location.">
                        <Toggle label="Show the announcement bar" checked={announcement.enabled} onChange={(event) => patch('announcement', { enabled: event.target.checked })} />
                        <Textarea label="Message" rows={2} maxLength={140} value={announcement.text} error={err('announcement').text} onChange={(event) => patch('announcement', { text: event.target.value })} />
                        <div className="grid gap-4 md:grid-cols-[minmax(0,0.8fr)_minmax(0,1.2fr)]">
                            <TextInput label="Link label (optional)" maxLength={40} value={announcement.link_label} error={err('announcement').link_label} onChange={(event) => patch('announcement', { link_label: event.target.value })} />
                            <TextInput label="Link" placeholder="/contact or https://…" maxLength={190} value={announcement.link_url} error={err('announcement').link_url} help="A page on the site, https://, tel: or mailto:." onChange={(event) => patch('announcement', { link_url: event.target.value })} />
                        </div>
                    </SettingsCard>
                    <SettingsCard title="Preview" description={announcement.enabled ? 'How visitors will see it.' : 'Hidden — turn it on to show it on the website.'}>
                        <div className={cn('flex flex-wrap items-center justify-center gap-x-3 gap-y-1 rounded-card bg-navy px-5 py-3.5 text-center text-[14px] text-white', !announcement.enabled && 'opacity-50')}>
                            <Megaphone className="size-4 text-sky" aria-hidden="true" />
                            <span>{announcement.text || 'Your message appears here.'}</span>
                            {announcement.link_label && <span className="font-bold text-sky underline underline-offset-2">{announcement.link_label} →</span>}
                        </div>
                    </SettingsCard>
                </div>
            )}

            {tab === 'disclaimers' && (
                <div className="grid items-start gap-6 xl:grid-cols-2">
                    <CrisisCard value={disclaimers.crisis} error={err('disclaimers').crisis} onChange={(value) => patch('disclaimers', { crisis: value })} />
                    <SettingsCard title="Short crisis line" description="The compact footer and mobile menu.">
                        <Textarea label="Text" rows={3} maxLength={160} value={disclaimers.crisis_short} error={err('disclaimers').crisis_short} onChange={(event) => patch('disclaimers', { crisis_short: event.target.value })} />
                    </SettingsCard>
                    <SettingsCard title="Insurance disclaimer" description="Under the partner logo wall.">
                        <Textarea label="Text" rows={4} maxLength={500} value={disclaimers.insurance} error={err('disclaimers').insurance} onChange={(event) => patch('disclaimers', { insurance: event.target.value })} />
                    </SettingsCard>
                    <SettingsCard title="Testimonials disclaimer" description="Under patient voices.">
                        <Textarea label="Text" rows={3} maxLength={300} value={disclaimers.testimonials} error={err('disclaimers').testimonials} onChange={(event) => patch('disclaimers', { testimonials: event.target.value })} />
                    </SettingsCard>
                    <SettingsCard title="Self-check disclaimer" description="Beside the quiz.">
                        <Textarea label="Text" rows={3} maxLength={300} value={disclaimers.quiz} error={err('disclaimers').quiz} onChange={(event) => patch('disclaimers', { quiz: event.target.value })} />
                    </SettingsCard>
                </div>
            )}

            {tab === 'privacy' && (
                <div className="grid items-start gap-6 xl:grid-cols-2">
                    <SettingsCard title="Consultation request retention" description="How long requests from the website are kept before they are permanently deleted.">
                        <TextInput
                            label="Keep requests for (days)"
                            type="number"
                            min={30}
                            max={3650}
                            value={privacy.consultation_retention_days}
                            error={err('privacy').consultation_retention_days}
                            help="Between 30 and 3,650 days. The clean-up job starts with consultation requests (Phase 3); requests are health information, so keep only what you need."
                            onChange={(event) => patch('privacy', { consultation_retention_days: Number(event.target.value) })}
                        />
                    </SettingsCard>
                    <SettingsCard
                        title="Staff notifications"
                        description="Where new-request alerts go, and how much of the request they carry."
                        aside={privacy.staff_alert_details ? <Badge tone="warning" dot>With details</Badge> : <Badge tone="success" dot>Link only</Badge>}
                    >
                        <TextInput
                            label="Notification email"
                            type="email"
                            maxLength={190}
                            value={privacy.staff_notification_email}
                            error={err('privacy').staff_notification_email}
                            help="Leave empty to send no alert at all. New requests still appear in the inbox."
                            onChange={(event) => patch('privacy', { staff_notification_email: event.target.value })}
                        />
                        <Toggle
                            label="Include the request in the alert"
                            description="The alert spells out the name, phone, email, insurance, answers and message. That is health information: it then sits in the mailbox and with your email provider, who needs a BAA. Turn this off to send the reference and a link only."
                            checked={privacy.staff_alert_details}
                            onChange={(event) => patch('privacy', { staff_alert_details: event.target.checked })}
                        />
                    </SettingsCard>
                </div>
            )}

            {tab === 'security' && (
                <div className="grid items-start gap-6 xl:grid-cols-2">
                    <SettingsCard
                        title="Two-factor sign-in"
                        description="Ask for a code from an authenticator app after the password, and make roles that require two-factor set it up."
                        aside={security.two_factor_login ? <Badge tone="success" dot>On</Badge> : <Badge tone="warning" dot>Off</Badge>}
                    >
                        <Toggle
                            label="Require two-factor at sign-in"
                            description="Off by default. While off, everyone signs in with email and password only; codes already set up are kept and work again when you turn this back on."
                            checked={security.two_factor_login}
                            onChange={(event) => patch('security', { two_factor_login: event.target.checked })}
                        />
                        <p className="flex items-start gap-2.5 text-admin-small text-ink-soft">
                            {security.two_factor_login ? (
                                <ShieldCheck className="mt-0.5 size-4 shrink-0 text-success" aria-hidden="true" />
                            ) : (
                                <TriangleAlert className="mt-0.5 size-4 shrink-0 text-warning" aria-hidden="true" />
                            )}
                            {security.two_factor_login
                                ? 'People in roles that require two-factor set it up at their next request; choose those roles under Users → Roles.'
                                : 'Staff who can see consultation requests handle patient contact details. Two-factor is strongly advised.'}
                        </p>
                    </SettingsCard>
                </div>
            )}

            {tab === 'cache' && <CachePanel />}

            <ConfirmDialog
                open={blocker.state === 'blocked'}
                title="Leave without saving?"
                description="Your changes to clinic settings haven’t been saved."
                confirmLabel="Leave"
                onClose={() => blocker.reset?.()}
                onConfirm={() => blocker.proceed?.()}
            />
        </>
    );
}

/** Not a settings group: each button acts at once, so it sits outside "Save changes". */
function CachePanel() {
    const toast = useToast();
    const clear = useClearCache();
    const [confirming, setConfirming] = useState(false);
    const [last, setLast] = useState<Partial<Record<CacheScope, string>>>({});

    const run = async (scope: CacheScope) => {
        try {
            const result = await clear.mutateAsync(scope);
            setLast((current) => ({ ...current, [scope]: result.cleared_at }));
            toast.success(
                scope === 'website' ? 'Website cache cleared' : 'Application cache cleared',
                result.website_connected ? 'The website shows the latest content on the next visit.' : 'Website revalidation isn’t set up here, so pages refresh within an hour.',
            );
        } catch (error) {
            toast.error('Cache wasn’t cleared', error instanceof ApiError ? error.humanMessage : undefined);
        } finally {
            setConfirming(false);
        }
    };

    const busy = (scope: CacheScope) => clear.isPending && clear.variables === scope;
    const clearedAt = (scope: CacheScope) => {
        const at = last[scope];

        return at ? <Badge tone="success" dot>Cleared {new Date(at).toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' })}</Badge> : undefined;
    };

    return (
        <div className="grid items-start gap-6 xl:grid-cols-2">
            <SettingsCard title="Website cache" description="Use when a published change isn’t showing on uptms.com yet. Safe at any time." aside={clearedAt('website')}>
                <p className="text-admin-small text-ink-soft">Drops the saved copies of pages, posts, menus, settings and SEO data, and asks the website to rebuild every page on its next visit.</p>
                <Button variant="soft" className="self-start" icon={<Globe className="size-4" />} loading={busy('website')} disabled={clear.isPending} onClick={() => void run('website')}>
                    Clear website cache
                </Button>
            </SettingsCard>
            <SettingsCard title="Application cache" description="For troubleshooting the admin itself. Also clears the website cache." aside={clearedAt('application')}>
                <p className="text-admin-small text-ink-soft">Empties everything the server keeps in its cache, including compiled views and sign-in attempt limits. Nobody is signed out and no data is lost; the first few page loads afterwards are slower.</p>
                <Button variant="soft" className="self-start" icon={<DatabaseZap className="size-4" />} loading={busy('application')} disabled={clear.isPending} onClick={() => setConfirming(true)}>
                    Clear application cache
                </Button>
            </SettingsCard>
            <ConfirmDialog
                open={confirming}
                tone="primary"
                title="Clear the application cache?"
                description="Everything rebuilds automatically. Pages may load a little slower for a minute."
                confirmLabel="Clear cache"
                busy={busy('application')}
                onClose={() => setConfirming(false)}
                onConfirm={() => void run('application')}
            />
        </div>
    );
}

function CrisisCard({ value, error, onChange }: { value: string; error: string | undefined; onChange: (value: string) => void }) {
    return (
        <SettingsCard title="Crisis disclaimer" description="Shown in every page footer." aside={<Badge tone="danger">Required</Badge>}>
            <Textarea label="Text" rows={3} maxLength={240} value={value} error={error} help="Can’t be turned off." onChange={(event) => onChange(event.target.value)} />
        </SettingsCard>
    );
}

function MapTile({ latitude, longitude }: { latitude: number | string | null; longitude: number | string | null }) {
    const lat = latitude === null || latitude === '' ? null : Number(latitude);
    const lng = longitude === null || longitude === '' ? null : Number(longitude);
    const valid = lat !== null && lng !== null && Number.isFinite(lat) && Number.isFinite(lng);

    return (
        <div className="relative flex h-44 items-center justify-center overflow-hidden rounded-card bg-sky-soft">
            <svg aria-hidden="true" className="absolute inset-0 size-full text-white" preserveAspectRatio="none" viewBox="0 0 400 160">
                <path d="M0 110 C120 90 180 130 400 70" stroke="currentColor" strokeWidth="10" fill="none" />
                <path d="M140 0 L190 160" stroke="currentColor" strokeWidth="8" fill="none" />
                <path d="M0 40 L400 55" stroke="currentColor" strokeWidth="5" fill="none" />
            </svg>
            {valid ? (
                <a
                    href={`https://www.google.com/maps?q=${lat},${lng}`}
                    target="_blank"
                    rel="noreferrer"
                    className="relative inline-flex items-center gap-2 rounded-full bg-navy px-4 py-2.5 text-[13px] font-semibold text-white shadow-[0_8px_20px_rgba(14,48,78,0.25)] hover:bg-sidebar-active"
                >
                    <MapIcon className="size-4" aria-hidden="true" />
                    {lat.toFixed(4)}, {lng.toFixed(4).replace('-', '−')} · check the pin
                    <ExternalLink className="size-3.5" aria-hidden="true" />
                </a>
            ) : (
                <span className="relative rounded-full bg-white px-4 py-2 text-admin-small text-ink-soft">Add coordinates to place the map pin.</span>
            )}
        </div>
    );
}

function firstWithPrefix(errors: Record<string, string>, prefix: string): string | undefined {
    return Object.entries(errors).find(([key]) => key === prefix || key.startsWith(`${prefix}.`))?.[1];
}
