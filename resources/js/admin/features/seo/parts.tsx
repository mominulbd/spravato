import type { ReactNode } from 'react';
import { useState } from 'react';
import { Card, cn } from '@shared/ui';
import { STATUS_DOT, lengthTone, scoreTone } from './format';
import type { Check } from './types';

const SCORE_TEXT = { success: 'text-success', warning: 'text-warning', danger: 'text-danger', neutral: 'text-ink-soft' } as const;

/** A list's SEO column: coloured dot + score, or "—" when there is nothing to score (e.g. in Trash). */
export function SeoScore({ score, className }: { score: number | null | undefined; className?: string }) {
    if (score === null || score === undefined) {
        return (
            <span className={cn('text-ink-soft', className)} title="No SEO score">
                <span className="sr-only">SEO score: </span>—
            </span>
        );
    }

    return (
        <span className={cn('inline-flex items-center gap-1.5 font-semibold', SCORE_TEXT[scoreTone(score)], className)}>
            <span aria-hidden="true" className="size-1.5 rounded-full bg-current" />
            <span className="sr-only">SEO score: </span>
            {score}
        </span>
    );
}

/** Card with the design's title + caption head. */
export function SeoCard({ title, description, actions, children, className }: { title?: string; description?: string; actions?: ReactNode; children: ReactNode; className?: string }) {
    return (
        <Card className={cn('flex min-w-0 flex-col gap-4 rounded-sheet p-6', className)}>
            {title && (
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div className="flex min-w-0 flex-col gap-0.5">
                        <h2 className="text-admin-card-title font-bold text-ink">{title}</h2>
                        {description && <p className="text-admin-small text-ink-soft">{description}</p>}
                    </div>
                    {actions}
                </div>
            )}
            {children}
        </Card>
    );
}

const RING = { success: 'stroke-success', warning: 'stroke-warning', danger: 'stroke-danger', neutral: 'stroke-ink-soft' } as const;

/** Score ring (Figma 08 / 12): the arc fills to the score. */
export function ScoreRing({ score, size = 64 }: { score: number; size?: number }) {
    const radius = size / 2 - 4;
    const circumference = 2 * Math.PI * radius;

    return (
        <span className="relative inline-flex shrink-0 items-center justify-center" style={{ width: size, height: size }} role="img" aria-label={`Score ${score} out of 100`}>
            <svg width={size} height={size} className="-rotate-90" aria-hidden="true">
                <circle cx={size / 2} cy={size / 2} r={radius} fill="none" strokeWidth="5" className="stroke-stone" />
                <circle cx={size / 2} cy={size / 2} r={radius} fill="none" strokeWidth="5" strokeLinecap="round" className={RING[scoreTone(score)]} strokeDasharray={circumference} strokeDashoffset={circumference * (1 - score / 100)} />
            </svg>
            <span className="absolute font-serif text-[22px] leading-none text-ink">{score}</span>
        </span>
    );
}

export function CheckList({ checks, collapsed = 6 }: { checks: Check[]; collapsed?: number }) {
    const [open, setOpen] = useState(false);
    const problems = checks.filter((check) => check.status !== 'good');
    const good = checks.filter((check) => check.status === 'good');
    const shownGood = open ? good : good.slice(0, Math.max(0, collapsed - problems.length));
    const hidden = good.length - shownGood.length;

    return (
        <div className="flex flex-col gap-3">
            {problems.length > 0 && <Group label={`Improvements · ${problems.length}`} checks={problems} />}
            {good.length > 0 && <Group label={`Good · ${good.length}`} checks={shownGood} />}
            {hidden > 0 && (
                <button type="button" onClick={() => setOpen(true)} className="self-start text-admin-body font-semibold text-royal hover:underline">
                    Show {hidden} more
                </button>
            )}
        </div>
    );
}

function Group({ label, checks }: { label: string; checks: Check[] }) {
    return (
        <div className="flex flex-col gap-2.5">
            <p className="font-display text-[10px] leading-[1.3] font-bold tracking-[1.1px] text-ink-soft uppercase">{label}</p>
            <ul className="flex flex-col gap-2.5">
                {checks.map((check) => (
                    <li key={check.key} className="flex items-start gap-2.5 text-admin-body text-ink">
                        <span aria-hidden="true" className={cn('mt-[7px] size-2 shrink-0 rounded-full', STATUS_DOT[check.status])} />
                        <span>
                            <span className="sr-only">{check.status === 'good' ? 'Good: ' : check.status === 'improve' ? 'Improve: ' : 'Problem: '}</span>
                            {check.text}
                        </span>
                    </li>
                ))}
            </ul>
        </div>
    );
}

const BAR = { good: 'bg-success', short: 'bg-warning', long: 'bg-danger' } as const;
const LABEL = { good: 'text-success', short: 'text-warning', long: 'text-danger' } as const;

/** Length meter under the title and description fields, measured in pixels like Google. */
export function LengthMeter({ chars, px, max, minChars }: { chars: number; px: number; max: number; minChars: number }) {
    const { tone, label } = lengthTone(px, max, minChars, chars);

    return (
        <div className="flex flex-col gap-1.5">
            <span className="h-1.5 w-full overflow-hidden rounded-full bg-stone" aria-hidden="true">
                <span className={cn('block h-full rounded-full transition-[width]', BAR[tone])} style={{ width: `${Math.min(100, (px / max) * 100)}%` }} />
            </span>
            <span className="flex justify-between gap-3 text-admin-small">
                <span className={LABEL[tone]}>{label}</span>
                <span className="text-ink-soft">
                    {chars} characters · {px} of {max} px
                </span>
            </span>
        </div>
    );
}
