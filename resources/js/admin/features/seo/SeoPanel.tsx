import { useRef, useState } from 'react';
import { Copy, ExternalLink, Link2, Monitor, Plus, Smartphone, Trash2 } from 'lucide-react';
import { Badge, Button, Select, TagInput, TextInput, Textarea, Toggle, cn, useToast } from '@shared/ui';
import { displayUrl, scoreLabel } from './format';
import { CheckList, LengthMeter, ScoreRing, SeoCard } from './parts';
import type { SeoDraft } from './useSeoDraft';

const VARIABLES = ['%title%', '%sep%', '%site%'] as const;

/** Figma 08: search appearance, indexing and medical review, with the live checklist beside them. */
export function SeoPanel({ draft, canEdit }: { draft: SeoDraft; canEdit: boolean }) {
    const { values, live, errors, set } = draft;
    const { subject, resolved, analysis } = live;
    const [device, setDevice] = useState<'desktop' | 'mobile'>('desktop');
    const [lens, setLens] = useState<'seo' | 'readability'>('seo');
    const titleRef = useRef<HTMLInputElement>(null);
    const toast = useToast();

    const insert = (variable: string) => {
        const input = titleRef.current;
        const current = values.meta_title ?? subject.template;
        const start = input?.selectionStart ?? current.length;
        const end = input?.selectionEnd ?? current.length;
        const next = `${current.slice(0, start)}${start > 0 && current[start - 1] !== ' ' ? ' ' : ''}${variable}${current.slice(end)}`;
        set('meta_title', next);
        requestAnimationFrame(() => input?.focus());
    };

    const score = lens === 'seo' ? analysis.score : analysis.readability;
    const citations = values.citations ?? [];

    return (
        <div className="grid items-start gap-5 xl:grid-cols-[minmax(0,1fr)_340px]">
            <fieldset className="flex min-w-0 flex-col gap-5" disabled={!canEdit}>
                <SeoCard
                    title="Search appearance"
                    description="How this page can look in Google results"
                    actions={
                        <div role="radiogroup" aria-label="Preview size" className="flex rounded-full bg-paper p-1">
                            {(['desktop', 'mobile'] as const).map((option) => (
                                <button key={option} type="button" role="radio" aria-checked={device === option} onClick={() => setDevice(option)} className={cn('flex h-7 items-center gap-1.5 rounded-full px-3 text-admin-small font-semibold', device === option ? 'bg-navy text-white' : 'text-ink-soft hover:text-ink')}>
                                    {option === 'desktop' ? <Monitor className="size-3.5" aria-hidden="true" /> : <Smartphone className="size-3.5" aria-hidden="true" />}
                                    {option === 'desktop' ? 'Desktop' : 'Mobile'}
                                </button>
                            ))}
                        </div>
                    }
                >
                    <div className={cn('flex flex-col gap-1.5 rounded-[16px] bg-paper p-5', device === 'mobile' && 'max-w-[380px]')} aria-label="Google result preview">
                        <div className="flex items-center gap-2.5">
                            <span aria-hidden="true" className="flex size-7 items-center justify-center rounded-full bg-white text-[11px] font-bold text-royal">
                                U
                            </span>
                            <span className="flex min-w-0 flex-col leading-tight">
                                <span className="truncate text-admin-small text-ink">{resolved.site_name}</span>
                                <span className="truncate text-[11px] text-ink-soft">{displayUrl(subject.url)}</span>
                            </span>
                        </div>
                        <p className={cn('text-[#1a0dab]', device === 'desktop' ? 'line-clamp-1 text-[20px] leading-[1.3]' : 'line-clamp-2 text-[18px] leading-[1.3]')}>{resolved.title}</p>
                        <p className={cn('text-admin-body text-ink-soft', device === 'desktop' ? 'line-clamp-2' : 'line-clamp-3')}>{resolved.description}</p>
                    </div>

                    <div className="flex flex-col gap-2">
                        <div className="flex flex-wrap items-end justify-between gap-2">
                            <label htmlFor="seo-title" className="text-admin-label font-semibold text-ink">
                                SEO title
                            </label>
                            <span className="flex items-center gap-1.5 text-admin-small text-ink-soft">
                                Insert
                                {VARIABLES.map((variable) => (
                                    <button key={variable} type="button" onClick={() => insert(variable)} className="rounded-full bg-sky-soft px-2.5 py-0.5 font-semibold text-royal hover:bg-sky">
                                        {variable}
                                    </button>
                                ))}
                            </span>
                        </div>
                        <input
                            ref={titleRef}
                            id="seo-title"
                            className="h-11 w-full rounded-control border border-line bg-white px-3.5 text-admin-body text-ink placeholder:text-ink-soft/70 focus:border-royal focus:ring-2 focus:ring-royal/20 focus:outline-none"
                            value={values.meta_title ?? ''}
                            placeholder={subject.template}
                            maxLength={190}
                            aria-invalid={errors.meta_title ? true : undefined}
                            onChange={(event) => set('meta_title', event.target.value === '' ? null : event.target.value)}
                        />
                        {errors.meta_title && <p className="text-admin-small text-danger">{errors.meta_title}</p>}
                        <LengthMeter chars={analysis.title.chars} px={analysis.title.px} max={analysis.title.max_px} minChars={30} />
                    </div>

                    <div className="flex flex-col gap-1.5">
                        <span className="text-admin-label font-semibold text-ink">URL</span>
                        <p className="flex h-11 items-center truncate rounded-control border border-line bg-paper px-3.5 text-admin-body text-ink-soft">{subject.url}</p>
                        <p className="text-admin-small text-ink-soft">{subject.is_home ? 'The home page always lives at the root.' : 'Change the address where the content is edited. A 301 redirect from the old address is added automatically.'}</p>
                    </div>

                    <div className="flex flex-col gap-2">
                        <Textarea
                            label="Meta description"
                            rows={3}
                            maxLength={320}
                            value={values.meta_description ?? ''}
                            placeholder={resolved.default_description}
                            onChange={(event) => set('meta_description', event.target.value === '' ? null : event.target.value)}
                            help="Describe the page in plain words and include the focus keyphrase once."
                            error={errors.meta_description}
                        />
                        <LengthMeter chars={analysis.description.chars} px={analysis.description.px} max={analysis.description.max_px} minChars={100} />
                    </div>

                    <TextInput
                        label="Focus keyphrase"
                        value={values.focus_keyphrase ?? ''}
                        maxLength={100}
                        onChange={(event) => set('focus_keyphrase', event.target.value === '' ? null : event.target.value)}
                        help={values.focus_keyphrase ? `Used ${analysis.keyphrase_uses}× on the page` : 'The search phrase this page should be found for'}
                        error={errors.focus_keyphrase}
                    />
                    <TagInput label="Related keyphrases" value={values.keyphrases ?? []} onChange={(next) => set('keyphrases', next)} max={8} maxLength={100} placeholder="Add keyphrase" />
                </SeoCard>

                <SeoCard title="Indexing & canonical" description="Controls for search engines">
                    <div className="flex flex-col divide-y divide-line">
                        <ToggleRow label="Show this page in search results" help="Adds index to the robots meta tag" checked={values.robots_index} onChange={(on) => set('robots_index', on)} />
                        <ToggleRow label="Let search engines follow links" help="Adds follow to the robots meta tag" checked={values.robots_follow} onChange={(on) => set('robots_follow', on)} />
                        <ToggleRow label="Include in XML sitemap" help={values.robots_index ? 'Listed in /sitemap.xml' : 'Hidden pages are never listed'} checked={values.in_sitemap && values.robots_index} disabled={!values.robots_index} onChange={(on) => set('in_sitemap', on)} />
                    </div>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <TextInput label="Canonical URL" value={values.canonical_url ?? ''} placeholder={subject.url} onChange={(event) => set('canonical_url', event.target.value === '' ? null : event.target.value)} error={errors.canonical_url} help="Leave empty unless another address holds the original" />
                        <TextInput label="Breadcrumb title" value={values.breadcrumb_title ?? ''} placeholder={subject.title} maxLength={80} onChange={(event) => set('breadcrumb_title', event.target.value === '' ? null : event.target.value)} />
                        <Select label="Image preview size" value={values.max_image_preview} onChange={(event) => set('max_image_preview', event.target.value as 'large' | 'standard' | 'none')}>
                            <option value="large">Large</option>
                            <option value="standard">Standard</option>
                            <option value="none">None</option>
                        </Select>
                        <Select label="Snippet length" value={values.max_snippet === null ? '' : String(values.max_snippet)} onChange={(event) => set('max_snippet', event.target.value === '' ? null : Number(event.target.value))}>
                            <option value="">No limit</option>
                            <option value="160">160 characters</option>
                            <option value="80">80 characters</option>
                            <option value="0">No snippet</option>
                        </Select>
                    </div>
                </SeoCard>

                <SeoCard
                    title="Medical review"
                    description="Shown on health pages and in the schema for trust (E-E-A-T)"
                    actions={values.last_reviewed_at ? <Badge tone="info" dot>Due {dueDate(values.last_reviewed_at)}</Badge> : <Badge tone="warning" dot>Not reviewed</Badge>}
                >
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Select label="Reviewed by" value={values.reviewed_by ?? ''} onChange={(event) => set('reviewed_by', event.target.value === '' ? null : Number(event.target.value))} error={errors.reviewed_by}>
                            <option value="">Not reviewed</option>
                            {live.reviewers.map((person) => (
                                <option key={person.id} value={person.id}>
                                    {person.name}
                                </option>
                            ))}
                        </Select>
                        <TextInput label="Last reviewed" type="date" value={values.last_reviewed_at ?? ''} max={new Date().toISOString().slice(0, 10)} onChange={(event) => set('last_reviewed_at', event.target.value === '' ? null : event.target.value)} error={errors.last_reviewed_at} />
                    </div>
                    <div className="flex flex-col gap-2">
                        <span className="text-admin-label font-semibold text-ink">Sources cited</span>
                        {citations.map((citation, index) => (
                            <div key={index} className="flex items-start gap-3 rounded-[14px] bg-paper p-3.5">
                                <Link2 className="mt-3 size-4 shrink-0 text-royal" aria-hidden="true" />
                                <div className="grid min-w-0 flex-1 gap-2 sm:grid-cols-2">
                                    <TextInput label={<span className="sr-only">Source name</span>} placeholder="National Institute of Mental Health — Depression" value={citation.label} onChange={(event) => set('citations', citations.map((c, i) => (i === index ? { ...c, label: event.target.value } : c)))} error={errors[`citations.${index}.label`]} />
                                    <TextInput label={<span className="sr-only">Source address</span>} placeholder="https://www.nimh.nih.gov/…" value={citation.url} onChange={(event) => set('citations', citations.map((c, i) => (i === index ? { ...c, url: event.target.value } : c)))} error={errors[`citations.${index}.url`]} />
                                </div>
                                {citation.url.startsWith('https://') && (
                                    <a href={citation.url} target="_blank" rel="noreferrer" className="mt-2 rounded-full p-1.5 text-ink-soft hover:bg-white hover:text-royal" aria-label={`Open ${citation.label || 'source'}`}>
                                        <ExternalLink className="size-4" aria-hidden="true" />
                                    </a>
                                )}
                                <button type="button" onClick={() => set('citations', citations.filter((_, i) => i !== index))} className="mt-2 rounded-full p-1.5 text-ink-soft hover:bg-white hover:text-danger" aria-label="Remove source">
                                    <Trash2 className="size-4" aria-hidden="true" />
                                </button>
                            </div>
                        ))}
                        {citations.length < 12 && (
                            <Button variant="soft" size="sm" className="self-start" icon={<Plus className="size-4" aria-hidden="true" />} onClick={() => set('citations', [...citations, { label: '', url: '' }])}>
                                Add source
                            </Button>
                        )}
                    </div>
                </SeoCard>
            </fieldset>

            <div className="flex min-w-0 flex-col gap-5 xl:sticky xl:top-6">
                <SeoCard>
                    <div className="flex items-center gap-4">
                        <ScoreRing score={score} />
                        <div className="flex flex-col">
                            <span className="font-serif text-[24px] leading-tight text-ink">{scoreLabel(score)}</span>
                            <span className="text-admin-small text-ink-soft">{lens === 'seo' ? 'SEO score' : 'Readability'} · {draft.checking ? 'checking…' : 'updated as you type'}</span>
                        </div>
                    </div>
                    <div role="tablist" aria-label="Checklist" className="grid grid-cols-2 rounded-full bg-paper p-1">
                        {(['seo', 'readability'] as const).map((option) => (
                            <button key={option} type="button" role="tab" aria-selected={lens === option} onClick={() => setLens(option)} className={cn('h-8 rounded-full text-admin-small font-semibold', lens === option ? 'bg-white text-ink shadow-sm' : 'text-ink-soft hover:text-ink')}>
                                {option === 'seo' ? `SEO · ${analysis.score}` : `Readability · ${analysis.readability}`}
                            </button>
                        ))}
                    </div>
                    <CheckList key={lens} checks={lens === 'seo' ? analysis.seo : analysis.readability_checks} />
                </SeoCard>

                <SeoCard title="Google Search Console" description="Clicks and position for this page">
                    <p className="rounded-[14px] bg-paper px-4 py-3.5 text-admin-body text-ink-soft">Connect Search Console in SEO settings to see clicks, impressions and position here.</p>
                </SeoCard>

                <SeoCard title="Internal links" description={live.links.incoming.length === 0 ? 'No other page links here yet' : `Linked from ${live.links.incoming.length} ${live.links.incoming.length === 1 ? 'page' : 'pages'}`}>
                    {live.links.suggestions.length === 0 ? (
                        <p className="text-admin-small text-ink-soft">No suggestions right now.</p>
                    ) : (
                        <div className="flex flex-col gap-2.5">
                            <p className="font-display text-[10px] leading-[1.3] font-bold tracking-[1.1px] text-ink-soft uppercase">Suggested</p>
                            {live.links.suggestions.map((suggestion) => (
                                <div key={suggestion.path} className="flex items-center justify-between gap-3 rounded-[14px] border border-line px-3.5 py-3">
                                    <div className="flex min-w-0 flex-col">
                                        <span className="truncate text-admin-body font-semibold text-ink">“{suggestion.title}”</span>
                                        <span className="truncate text-admin-small text-ink-soft">→ {suggestion.path}</span>
                                    </div>
                                    <Button
                                        variant="soft"
                                        size="sm"
                                        icon={<Copy className="size-3.5" aria-hidden="true" />}
                                        onClick={() => {
                                            void navigator.clipboard?.writeText(suggestion.path);
                                            toast.success('Link copied', `Paste ${suggestion.path} into a section’s link.`);
                                        }}
                                    >
                                        Copy
                                    </Button>
                                </div>
                            ))}
                        </div>
                    )}
                </SeoCard>
            </div>
        </div>
    );
}

function ToggleRow({ label, help, checked, disabled, onChange }: { label: string; help: string; checked: boolean; disabled?: boolean; onChange: (on: boolean) => void }) {
    return (
        <div className="flex items-center justify-between gap-4 py-3 first:pt-0 last:pb-0">
            <div className="flex flex-col">
                <span className="text-admin-body font-semibold text-ink">{label}</span>
                <span className="text-admin-small text-ink-soft">{help}</span>
            </div>
            <Toggle label={<span className="sr-only">{label}</span>} checked={checked} disabled={disabled} onChange={(event) => onChange(event.target.checked)} />
        </div>
    );
}

/** Health content is reviewed again six months after its last review. */
function dueDate(reviewed: string): string {
    const date = new Date(`${reviewed}T12:00:00`);
    date.setMonth(date.getMonth() + 6);

    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}
