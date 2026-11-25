import type { ReactNode } from 'react';
import { accentRuns, type ContentLink } from '@admin/lib/content';
import { cn } from '@shared/ui';
import type { BlockData, BlockTypeDef } from './types';

/**
 * The "Live preview" card of Figma 07: a small, faithful render of the
 * selected section in the website's palette. Tokens like {phone} are shown
 * as written; the website fills them in.
 */
function Headline({ text, accent, className }: { text: string; accent: string; className?: string }) {
    return (
        <p className={cn('font-serif tracking-[-0.01em]', className)}>
            {accentRuns(text).map((run, index) =>
                run.accent ? (
                    <em key={index} className={accent}>
                        {run.text}
                    </em>
                ) : (
                    <span key={index}>{run.text}</span>
                ),
            )}
        </p>
    );
}

const str = (value: unknown) => (typeof value === 'string' ? value : '');

function link(value: unknown): ContentLink | null {
    if (value === null || typeof value !== 'object') return null;
    const candidate = value as Partial<ContentLink>;

    return candidate.label ? { label: candidate.label, href: candidate.href ?? '' } : null;
}

function Pill({ children, tone }: { children: ReactNode; tone: 'light' | 'outline' | 'royal' | 'soft' }) {
    return (
        <span
            className={cn(
                'inline-flex max-w-full truncate rounded-full px-2.5 py-1 font-display text-[9px] leading-[1.2] font-bold',
                tone === 'light' && 'bg-white text-navy',
                tone === 'outline' && 'border border-white/80 text-white',
                tone === 'royal' && 'bg-royal text-white',
                tone === 'soft' && 'bg-sky-soft text-royal',
            )}
        >
            {children}
        </span>
    );
}

export function BlockPreview({ type, data }: { type: BlockTypeDef | undefined; data: BlockData }) {
    const key = type?.key ?? '';
    const headline = str(data.headline) || str(data.team_title);
    const primary = link(data.primary) ?? link(data.cta) ?? link(data.close_cta);
    const secondary = link(data.secondary) ?? link(data.link);

    if (key === 'hero' || (key === 'cta_band' && data.tone === 'royal')) {
        const royal = key === 'cta_band';

        return (
            <div className={cn('flex flex-col gap-2.5 rounded-[18px] p-5 text-white', royal ? 'bg-royal' : 'bg-navy')}>
                {str(data.eyebrow) && <p className={cn('font-display text-[8px] font-bold tracking-[0.1em] uppercase', royal ? 'text-sky' : 'text-sky')}>{str(data.eyebrow)}</p>}
                <Headline text={headline || 'Headline'} accent="text-sky" className="text-[20px] leading-[1.12]" />
                {str(data.subheading) && <p className="line-clamp-2 text-[10px] leading-[1.5] text-white/85">{str(data.subheading)}</p>}
                <div className="mt-1 flex flex-col items-start gap-1.5">
                    {primary && <Pill tone="light">{primary.label}</Pill>}
                    {secondary && <Pill tone="outline">{secondary.label}</Pill>}
                </div>
            </div>
        );
    }

    if (key === 'cta_band') {
        return (
            <div className="flex flex-col items-center gap-2.5 rounded-[18px] bg-sky p-5 text-center text-ink">
                {str(data.eyebrow) && <p className="font-display text-[8px] font-bold tracking-[0.1em] text-royal uppercase">{str(data.eyebrow)}</p>}
                <Headline text={headline || 'Headline'} accent="text-royal" className="text-[20px] leading-[1.12]" />
                {str(data.body) && <p className="line-clamp-2 text-[10px] leading-[1.5] text-ink-soft">{str(data.body)}</p>}
                {primary && <Pill tone="royal">{primary.label}</Pill>}
            </div>
        );
    }

    const number = str(data.rail);
    const items = Array.isArray(data.panel_items) ? (data.panel_items as string[]) : Array.isArray(data.questions) ? (data.questions as string[]) : Array.isArray(data.items) ? (data.items as string[]) : [];
    const body = str(data.subheading) || str(data.intro) || str(data.body).split(/\n\s*\n/)[0] || '';

    return (
        <div className="flex flex-col gap-2.5 rounded-[18px] border border-line bg-white p-5">
            {number && (
                <p className="flex items-center gap-2 font-display text-[8px] font-bold tracking-[0.1em] text-royal uppercase">
                    <span aria-hidden="true" className="h-px w-5 bg-royal" />
                    {number}
                </p>
            )}
            {!number && str(data.eyebrow) && <p className="font-display text-[8px] font-bold tracking-[0.1em] text-royal uppercase">{str(data.eyebrow)}</p>}
            <Headline text={headline || type?.label || 'Section'} accent="text-royal" className="text-[18px] leading-[1.15] text-ink" />
            {body && <p className="line-clamp-3 text-[10px] leading-[1.5] text-ink-soft">{body.replace(/\*\*?/g, '')}</p>}
            {items.length > 0 && (
                <ul className="flex flex-col gap-1 rounded-[12px] bg-sky-soft p-3">
                    {items.slice(0, 4).map((item, index) => (
                        <li key={index} className="flex gap-1.5 text-[9px] leading-[1.4] text-ink">
                            <span aria-hidden="true" className="mt-[3px] size-1.5 shrink-0 rounded-full bg-royal" />
                            <span className="line-clamp-1">{item}</span>
                        </li>
                    ))}
                </ul>
            )}
            {primary && (
                <div>
                    <Pill tone={key === 'copy_panel' ? 'soft' : 'royal'}>{primary.label}</Pill>
                </div>
            )}
            {type && !['copy_panel', 'rich_text', 'media_text', 'condition_feature'].includes(key) && <p className="text-[9px] text-ink-soft">{type.description}</p>}
        </div>
    );
}
