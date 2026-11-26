import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Check, ShieldCheck, TriangleAlert } from 'lucide-react';
import { Link, useSearchParams } from 'react-router';
import { ApiError, api, getData } from '@shared/api/client';
import { Badge, Button, Card, ErrorState, SegmentedTabs, Select, Skeleton, TextInput, Toggle, cn, useToast } from '@shared/ui';
import { ImageField } from '@admin/features/media/ImageField';
import { PageHeader } from '@admin/components/PageHeader';
import { renderTemplate } from './format';
import { SeoCard } from './parts';

type Group = 'general' | 'organization' | 'social' | 'verification' | 'analytics';

interface SettingsPayload {
    values: Record<string, unknown>;
    separators: string[];
    /** Paths the website keeps tag-free while the exclusion is on. */
    sensitive_paths: string[];
    examples: Record<'page', string>;
    clinic: Record<string, unknown> & { name: string; telephone?: string; address?: Record<string, string>; geo?: { latitude: number; longitude: number } };
    site_url: string;
}

const TABS: { value: Group; label: string }[] = [
    { value: 'general', label: 'General' },
    { value: 'organization', label: 'Organization' },
    { value: 'social', label: 'Social profiles' },
    { value: 'verification', label: 'Verification' },
    { value: 'analytics', label: 'Analytics' },
];

const TEMPLATES: { key: string; label: string; example: Record<string, string> }[] = [
    { key: 'template_page', label: 'Pages', example: { title: 'TMS therapy' } },
];

/** Figma 23: site-wide defaults that every page's SEO tab falls back to. */
export function SeoSettingsPage() {
    const [params, setParams] = useSearchParams();
    const tab = (params.get('tab') as Group | null) ?? 'general';
    const settings = useQuery({ queryKey: ['seo', 'settings'], queryFn: () => getData<SettingsPayload>('admin/seo-settings') });

    return (
        <>
            <PageHeader trail={['SEO', 'Settings']} title="SEO" accent="settings." description="Site-wide defaults. Individual pages can override these in their SEO tab." />
            <SegmentedTabs label="SEO settings" value={tab} options={TABS} onChange={(next) => setParams(next === 'general' ? {} : { tab: next }, { replace: true })} />
            {settings.isPending && <Skeleton className="h-[480px] w-full rounded-sheet" />}
            {settings.isError && <ErrorState title="Settings didn’t load" description={settings.error instanceof ApiError ? settings.error.humanMessage : 'Try again in a moment.'} onRetry={() => void settings.refetch()} />}
            {settings.data && <Form key={tab} group={tab} data={settings.data} />}
        </>
    );
}

function Form({ group, data }: { group: Group; data: SettingsPayload }) {
    const toast = useToast();
    const queryClient = useQueryClient();
    const [values, setValues] = useState<Record<string, unknown>>(data.values);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const set = (key: string, value: unknown) => {
        setValues((current) => ({ ...current, [key]: value }));
        setErrors((current) => Object.fromEntries(Object.entries(current).filter(([field]) => field !== key)));
    };
    const text = (key: string) => (typeof values[key] === 'string' ? (values[key] as string) : '');
    const bool = (key: string) => values[key] === true;

    const keys: Record<Group, string[]> = {
        general: ['site_name', 'separator', 'template_page', 'default_og_image_id'],
        organization: ['schema_type', 'medical_specialty', 'logo_id', 'price_range'],
        social: ['x_handle'],
        verification: ['google', 'bing'],
        analytics: ['gtm_enabled', 'gtm_container_id', 'gtm_exclude_sensitive'],
    };

    const save = useMutation({
        mutationFn: () => api.put<SettingsPayload>(`admin/seo-settings/${group}`, Object.fromEntries(keys[group].map((key) => [key, values[key] === '' ? null : values[key]]))),
        onSuccess: (response) => {
            queryClient.setQueryData(['seo', 'settings'], response.data);
            setValues(response.data.values);
            setErrors({});
            toast.success('SEO settings saved', 'The website updates within seconds.');
        },
        onError: (error) => {
            if (error instanceof ApiError && error.code === 'VALIDATION_FAILED') setErrors(Object.fromEntries(Object.entries(error.fieldErrors).map(([key, messages]) => [key, messages[0] ?? ''])));
            else toast.error('Not saved', error instanceof ApiError ? error.humanMessage : undefined);
        },
    });

    const separator = text('separator') || '|';
    const site = text('site_name');
    const clinic = data.clinic;
    const tagsOn = bool('gtm_enabled') && text('gtm_container_id') !== '';
    const tagsOnSensitive = tagsOn && !bool('gtm_exclude_sensitive');
    const sensitive = data.sensitive_paths.map((path) => (path === '/' ? 'the Spravato page (/)' : path)).join(' and ');

    return (
        <div className="grid items-start gap-5 xl:grid-cols-[minmax(0,1fr)_360px]">
            <form
                className="flex min-w-0 flex-col gap-5"
                onSubmit={(event) => {
                    event.preventDefault();
                    save.mutate();
                }}
            >
                {group === 'general' && (
                    <>
                        <SeoCard title="Title templates" description="How SEO titles are built when a page doesn’t set its own">
                            <div className="flex flex-wrap items-end gap-4">
                                <TextInput label="Site name" className="min-w-[220px] flex-1" value={text('site_name')} maxLength={60} onChange={(event) => set('site_name', event.target.value)} error={errors.site_name} />
                                <div className="flex flex-col gap-1.5">
                                    <span className="text-admin-label font-semibold text-ink">Separator</span>
                                    <div role="radiogroup" aria-label="Separator" className="flex gap-2">
                                        {data.separators.map((option) => (
                                            <button key={option} type="button" role="radio" aria-checked={separator === option} onClick={() => set('separator', option)} className={cn('flex size-11 items-center justify-center rounded-control border text-[18px]', separator === option ? 'border-navy bg-navy text-white' : 'border-line bg-white text-ink hover:border-ink-soft')}>
                                                {option}
                                            </button>
                                        ))}
                                    </div>
                                </div>
                            </div>
                            {TEMPLATES.map((template) => (
                                <div key={template.key} className="flex flex-col gap-2 rounded-[16px] bg-paper p-4">
                                    <div className="flex flex-wrap items-baseline justify-between gap-2">
                                        <label htmlFor={template.key} className="text-admin-label font-semibold text-ink">
                                            {template.label}
                                        </label>
                                        <span className="text-admin-small text-ink-soft">e.g. {renderTemplate(text(template.key), { ...template.example, site }, separator)}</span>
                                    </div>
                                    <input id={template.key} value={text(template.key)} maxLength={120} onChange={(event) => set(template.key, event.target.value)} aria-invalid={errors[template.key] ? true : undefined} className="h-11 w-full rounded-control border border-line bg-white px-3.5 font-mono text-[13px] text-ink focus:border-royal focus:ring-2 focus:ring-royal/20 focus:outline-none" />
                                    {errors[template.key] && <p className="text-admin-small text-danger">{errors[template.key]?.includes('format') ? 'Templates must include %title%.' : errors[template.key]}</p>}
                                </div>
                            ))}
                            <p className="text-admin-small text-ink-soft">Variables: %title%, %sep%, %site%, and %category% on posts.</p>
                        </SeoCard>
                        <SeoCard title="Default share image" description="Used when a page has no image of its own">
                            <ImageField label="Image" value={typeof values.default_og_image_id === 'number' ? values.default_og_image_id : null} onChange={(id) => set('default_og_image_id', id)} help="1200 × 630 shows best on every network." error={errors.default_og_image_id} />
                        </SeoCard>
                    </>
                )}

                {group === 'organization' && (
                    <SeoCard title="Organization" description="The clinic node every page’s JSON-LD starts with">
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Select label="Schema type" value={text('schema_type')} onChange={(event) => set('schema_type', event.target.value)} error={errors.schema_type}>
                                <option value="MedicalClinic">MedicalClinic</option>
                                <option value="MedicalBusiness">MedicalBusiness</option>
                                <option value="Physician">Physician (solo practice)</option>
                            </Select>
                            <TextInput label="Medical specialty" value={text('medical_specialty')} maxLength={60} onChange={(event) => set('medical_specialty', event.target.value)} help="schema.org specialty, e.g. Psychiatric" error={errors.medical_specialty} />
                            <TextInput label="Price range" value={text('price_range')} maxLength={20} placeholder="Optional, e.g. $$" onChange={(event) => set('price_range', event.target.value)} error={errors.price_range} />
                        </div>
                        <ImageField label="Logo" value={typeof values.logo_id === 'number' ? values.logo_id : null} onChange={(id) => set('logo_id', id)} help="A square or wide PNG, shown by Google beside the clinic’s name." error={errors.logo_id} />
                    </SeoCard>
                )}

                {group === 'social' && (
                    <SeoCard title="Social profiles" description="Profile links are listed in the clinic’s JSON-LD as sameAs">
                        <TextInput label="X (Twitter) handle" value={text('x_handle')} placeholder="@uptms" maxLength={30} onChange={(event) => set('x_handle', event.target.value)} help="Shown as twitter:site on every page" error={errors.x_handle} />
                        <p className="rounded-[14px] bg-paper px-4 py-3.5 text-admin-body text-ink-soft">
                            Facebook, Instagram, LinkedIn and YouTube links live in{' '}
                            <Link to="/settings" className="font-semibold text-royal hover:underline">
                                Clinic settings
                            </Link>
                            , so the website footer and the schema always match.
                        </p>
                    </SeoCard>
                )}

                {group === 'verification' && (
                    <SeoCard title="Site verification" description="Codes from each search engine’s webmaster tools, added as meta tags">
                        <TextInput label="Google Search Console" value={text('google')} placeholder="The content value of google-site-verification" onChange={(event) => set('google', event.target.value.trim())} error={errors.google} />
                        <TextInput label="Bing Webmaster Tools" value={text('bing')} placeholder="The content value of msvalidate.01" onChange={(event) => set('bing', event.target.value.trim())} error={errors.bing} />
                    </SeoCard>
                )}

                {group === 'analytics' && (
                    <SeoCard
                        title="Google Tag Manager"
                        description="One container for the public website’s analytics and marketing tags"
                        actions={
                            bool('gtm_enabled') ? (
                                tagsOn ? (
                                    <Badge tone="success" dot>
                                        On
                                    </Badge>
                                ) : (
                                    <Badge tone="warning" dot>
                                        Needs a container ID
                                    </Badge>
                                )
                            ) : (
                                <Badge tone="neutral" dot>
                                    Off
                                </Badge>
                            )
                        }
                    >
                        <Toggle
                            label="Load Google Tag Manager on the website"
                            description="Off by default: the website then loads no third-party script except Turnstile on the contact form. Turning this on loads gtm.js in the head of every page and starts the dataLayer, so whatever the container holds runs in patients’ browsers."
                            checked={bool('gtm_enabled')}
                            onChange={(event) => set('gtm_enabled', event.target.checked)}
                        />
                        <TextInput
                            label="Container ID"
                            value={text('gtm_container_id')}
                            placeholder="GTM-XXXXXXX"
                            maxLength={20}
                            className="max-w-[280px]"
                            onChange={(event) => set('gtm_container_id', event.target.value.trim().toUpperCase())}
                            help="From Tag Manager → Admin → Container settings."
                            error={errors.gtm_container_id ? (errors.gtm_container_id.includes('format') ? 'A container ID looks like GTM-M4Q7S9NS.' : errors.gtm_container_id) : undefined}
                        />
                        <Toggle
                            label={`Keep tags off ${sensitive}`}
                            description={`No container is loaded on ${sensitive}, so nothing a patient types into the consultation dialog can reach Google. Required unless your Tag Manager vendors are covered by a BAA.`}
                            checked={bool('gtm_exclude_sensitive')}
                            onChange={(event) => set('gtm_exclude_sensitive', event.target.checked)}
                        />
                        {tagsOnSensitive && (
                            <p className="flex items-start gap-2.5 rounded-card bg-warning-soft px-4 py-3.5 text-admin-small text-warning" role="status">
                                <TriangleAlert className="mt-px size-4 shrink-0" aria-hidden="true" />
                                Tags will fire on {sensitive} too. HHS online-tracking guidance treats that as disclosing health information to Google, so turn the exclusion back on unless a BAA covers it.
                            </p>
                        )}
                        <p className="text-admin-small text-ink-soft">The website reads this within seconds of saving. Consent and the tags themselves are managed in Tag Manager, not here.</p>
                    </SeoCard>
                )}

                <div className="flex justify-end">
                    <Button type="submit" icon={<Check className="size-[18px]" aria-hidden="true" />} loading={save.isPending}>
                        Save settings
                    </Button>
                </div>
            </form>

            <div className="flex min-w-0 flex-col gap-5">
                <Card className="flex flex-col gap-3.5 rounded-sheet p-6">
                    <div>
                        <h2 className="text-admin-card-title font-bold text-ink">Clinic identity</h2>
                        <p className="text-admin-small text-ink-soft">Feeds the MedicalClinic schema and Google’s knowledge panel</p>
                    </div>
                    <dl className="flex flex-col gap-3">
                        <Fact label="Type">{String(clinic['@type'] ?? 'MedicalClinic')}</Fact>
                        <Fact label="Name">{clinic.name}</Fact>
                        {clinic.address && <Fact label="Address">{`${clinic.address.streetAddress}, ${clinic.address.addressLocality}, ${clinic.address.addressRegion} ${clinic.address.postalCode}`}</Fact>}
                        {clinic.telephone && <Fact label="Phone">{clinic.telephone}</Fact>}
                        {clinic.geo && <Fact label="Geo">{`${clinic.geo.latitude}, ${clinic.geo.longitude}`}</Fact>}
                    </dl>
                    <Link to="/settings" className="text-admin-body font-semibold text-royal hover:underline">
                        Edit in Clinic settings →
                    </Link>
                </Card>
                {tagsOnSensitive ? (
                    <p className="flex items-start gap-2.5 rounded-card bg-warning-soft px-4 py-3.5 text-admin-small text-warning">
                        <TriangleAlert className="mt-px size-4 shrink-0" aria-hidden="true" />
                        Health-privacy rule: tags must not load where a patient can type health details. Tag Manager is currently allowed on {sensitive}.
                    </p>
                ) : (
                    <p className="flex items-start gap-2.5 rounded-card bg-sky-soft px-4 py-3.5 text-admin-small text-ink">
                        <ShieldCheck className="mt-px size-4 shrink-0 text-royal" aria-hidden="true" />
                        Health-privacy rule: {tagsOn ? `Tag Manager loads on the website but never on ${sensitive}, so no answer or contact detail reaches Google.` : 'the website loads no analytics or ad pixels, and never on the page with the consultation dialog.'}
                    </p>
                )}
            </div>
        </div>
    );
}

function Fact({ label, children }: { label: string; children: string }) {
    return (
        <div className="flex flex-col gap-0.5">
            <dt className="font-display text-[10px] leading-[1.3] font-bold tracking-[1.1px] text-ink-soft uppercase">{label}</dt>
            <dd className="text-admin-body text-ink">{children}</dd>
        </div>
    );
}
