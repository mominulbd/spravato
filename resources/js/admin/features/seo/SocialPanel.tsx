import { useState } from 'react';
import { ExternalLink } from 'lucide-react';
import { Select, TextInput, Textarea, Toggle, cn } from '@shared/ui';
import { ImageField } from '@admin/features/media/ImageField';
import { SeoCard } from './parts';
import type { SeoDraft } from './useSeoDraft';

type Network = 'facebook' | 'linkedin' | 'x' | 'imessage';

/** Figma 09: what people see when the page is shared. */
export function SocialPanel({ draft, canEdit }: { draft: SeoDraft; canEdit: boolean }) {
    const { values, live, errors, set } = draft;
    const { resolved, subject } = live;
    const [network, setNetwork] = useState<Network>('facebook');
    const image = resolved.share_image;
    const title = values.og_custom ? (values.og_title ?? resolved.title) : resolved.title;
    const description = values.og_custom ? (values.og_description ?? resolved.description) : resolved.description;

    const checks = [
        image ? (image.width === 1200 && image.height === 630 ? { ok: true, text: 'Image is 1200 × 630' } : { ok: false, text: `Image is ${image.width ?? '?'} × ${image.height ?? '?'}; 1200 × 630 shows best everywhere` }) : { ok: false, text: 'No share image: add one here or a default in SEO settings' },
        title.length <= 60 ? { ok: true, text: 'Title fits in 60 characters' } : { ok: false, text: `Title is ${title.length} characters; apps cut it near 60` },
        description.length > 0 && description.length <= 200 ? { ok: true, text: 'Description reads well in previews' } : { ok: false, text: 'Keep the description to one or two short sentences' },
    ];

    return (
        <div className="grid items-start gap-5 xl:grid-cols-[minmax(0,1fr)_380px]">
            <fieldset className="flex min-w-0 flex-col gap-5" disabled={!canEdit}>
                <SeoCard title="Facebook, LinkedIn & messaging apps" description="Open Graph tags · used when someone shares the link">
                    <div className="flex items-center justify-between gap-4 border-b border-line pb-4">
                        <div className="flex flex-col">
                            <span className="text-admin-body font-semibold text-ink">Use the SEO title and description</span>
                            <span className="text-admin-small text-ink-soft">Turn off to write a share-specific version</span>
                        </div>
                        <Toggle label={<span className="sr-only">Use the SEO title and description</span>} checked={!values.og_custom} onChange={(event) => set('og_custom', !event.target.checked)} />
                    </div>
                    <TextInput label="Share title" value={values.og_custom ? (values.og_title ?? '') : resolved.title} disabled={!values.og_custom} maxLength={120} placeholder={resolved.title} onChange={(event) => set('og_title', event.target.value || null)} help={`${title.length} characters`} error={errors.og_title} />
                    <Textarea label="Share description" rows={3} value={values.og_custom ? (values.og_description ?? '') : resolved.description} disabled={!values.og_custom} maxLength={200} placeholder={resolved.description} onChange={(event) => set('og_description', event.target.value || null)} help="Aim for 1–2 short sentences." error={errors.og_description} />
                    <ImageField label="Share image" value={values.og_image_id} onChange={(id) => set('og_image_id', id)} help={values.og_image_id === null ? (image ? 'Using the page’s first image or the site default.' : 'Choose a 1200 × 630 image.') : '1200 × 630 shows best on every network.'} error={errors.og_image_id} />
                </SeoCard>

                <SeoCard title="X (Twitter)" description="Card tags · uses the share title, description and image above">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Select label="Card type" value={values.twitter_card} onChange={(event) => set('twitter_card', event.target.value as 'summary' | 'summary_large_image')}>
                            <option value="summary_large_image">Summary with large image</option>
                            <option value="summary">Summary</option>
                        </Select>
                        <TextInput label="Site handle" value={resolved.x_handle || ''} disabled placeholder="@uptms (optional)" help="Set in SEO settings → Social profiles" />
                    </div>
                </SeoCard>
            </fieldset>

            <div className="flex min-w-0 flex-col gap-5 xl:sticky xl:top-6">
                <SeoCard
                    title="Preview"
                    actions={
                        <div role="radiogroup" aria-label="Network" className="flex rounded-full bg-paper p-1">
                            {(['facebook', 'linkedin', 'x', 'imessage'] as const).map((option) => (
                                <button key={option} type="button" role="radio" aria-checked={network === option} onClick={() => setNetwork(option)} className={cn('h-7 rounded-full px-2.5 text-admin-small font-semibold', network === option ? 'bg-navy text-white' : 'text-ink-soft hover:text-ink')}>
                                    {{ facebook: 'Facebook', linkedin: 'LinkedIn', x: 'X', imessage: 'iMessage' }[option]}
                                </button>
                            ))}
                        </div>
                    }
                >
                    <div className={cn('overflow-hidden border border-line bg-paper', network === 'imessage' ? 'rounded-[18px]' : network === 'x' ? 'rounded-[16px]' : 'rounded-[10px]')}>
                        <div className={cn('relative flex w-full items-end bg-navy', network === 'x' && values.twitter_card === 'summary' ? 'aspect-square max-w-[120px]' : 'aspect-[1200/630]')}>
                            {image ? <img src={image.url} alt="" className="absolute inset-0 size-full object-cover" /> : <span className="p-5 font-serif text-[22px] leading-tight text-white">{subject.title}</span>}
                        </div>
                        <div className="flex flex-col gap-0.5 px-3.5 py-3">
                            {network !== 'imessage' && <span className="text-[11px] tracking-[0.04em] text-ink-soft uppercase">{resolved.site_host}</span>}
                            <span className="line-clamp-2 text-admin-body font-semibold text-ink">{title}</span>
                            {(network === 'facebook' || network === 'x') && <span className="line-clamp-2 text-admin-small text-ink-soft">{description}</span>}
                            {network === 'imessage' && <span className="text-[11px] text-ink-soft">{resolved.site_host}</span>}
                        </div>
                    </div>
                </SeoCard>

                <SeoCard title="Share checks">
                    <ul className="flex flex-col gap-2.5">
                        {checks.map((check) => (
                            <li key={check.text} className="flex items-start gap-2.5 text-admin-body text-ink">
                                <span aria-hidden="true" className={cn('mt-[7px] size-2 shrink-0 rounded-full', check.ok ? 'bg-success' : 'bg-warning')} />
                                {check.text}
                            </li>
                        ))}
                    </ul>
                    <a href={`https://developers.facebook.com/tools/debug/?q=${encodeURIComponent(subject.url)}`} target="_blank" rel="noreferrer" className="inline-flex items-center gap-1.5 self-start text-admin-body font-semibold text-royal hover:underline">
                        Refresh share caches <ExternalLink className="size-3.5" aria-hidden="true" />
                    </a>
                </SeoCard>
            </div>
        </div>
    );
}
