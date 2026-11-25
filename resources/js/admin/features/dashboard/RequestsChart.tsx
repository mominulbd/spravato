import { useState } from 'react';
import { cn } from '@shared/ui';

/**
 * Daily consultation requests (Figma 03): one series, so one hue and no
 * legend; today in royal, earlier days in sky. Each bar has a hover/focus
 * tooltip, and a visually hidden table carries the same numbers.
 */
export function RequestsChart({ days }: { days: { date: string; count: number }[] }) {
    const [active, setActive] = useState<number | null>(null);
    const max = Math.max(1, ...days.map((day) => day.count));
    const top = niceMax(max);
    const ticks = [top, Math.round((top * 2) / 3), Math.round(top / 3), 0];
    const labelEvery = days.length <= 7 ? 1 : days.length <= 30 ? 7 : 14;
    const gap = days.length > 60 ? 1 : 2;

    return (
        <figure className="flex flex-col gap-2">
            <div className="flex gap-3">
                <div className="flex h-[180px] flex-col justify-between text-right text-[11px] leading-none text-ink-soft" aria-hidden="true">
                    {ticks.map((tick, index) => (
                        <span key={index}>{tick}</span>
                    ))}
                </div>
                <div className="relative h-[180px] min-w-0 flex-1">
                    <div className="pointer-events-none absolute inset-0 flex flex-col justify-between" aria-hidden="true">
                        {ticks.map((tick, index) => (
                            <span key={index} className={cn('h-px w-full', tick === 0 ? 'bg-line' : 'bg-line/50')} />
                        ))}
                    </div>
                    <div className="absolute inset-0 flex items-end" style={{ gap }} onMouseLeave={() => setActive(null)} aria-hidden="true">
                        {days.map((day, index) => {
                            const today = index === days.length - 1;

                            return (
                                <div key={day.date} className="relative flex h-full min-w-0 flex-1 items-end" onMouseEnter={() => setActive(index)}>
                                    <span
                                        className={cn('w-full rounded-t-[4px] transition-opacity', today ? 'bg-royal' : 'bg-sky', active !== null && active !== index && 'opacity-60')}
                                        style={{ height: day.count === 0 ? 2 : `${(day.count / top) * 100}%` }}
                                    />
                                    {active === index && (
                                        <span className="pointer-events-none absolute bottom-full left-1/2 z-10 mb-1.5 -translate-x-1/2 rounded-[8px] bg-navy px-2.5 py-1.5 text-[12px] leading-tight whitespace-nowrap text-white shadow-lg">
                                            <span className="block font-semibold">{day.count === 1 ? '1 request' : `${day.count} requests`}</span>
                                            <span className="block text-white/70">{dateLabel(day.date, { weekday: 'short', month: 'short', day: 'numeric' })}</span>
                                        </span>
                                    )}
                                </div>
                            );
                        })}
                    </div>
                </div>
            </div>
            <div className="ml-7 flex text-[11px] text-ink-soft" aria-hidden="true">
                {days.map((day, index) => (
                    <span key={day.date} className="min-w-0 flex-1 overflow-visible whitespace-nowrap">
                        {index === days.length - 1 ? 'Today' : (days.length - 1 - index) % labelEvery === 0 && index < days.length - Math.ceil(labelEvery / 2) ? dateLabel(day.date, { month: 'short', day: 'numeric' }) : ''}
                    </span>
                ))}
            </div>
            <table className="sr-only">
                <caption>Consultation requests per day</caption>
                <thead>
                    <tr>
                        <th scope="col">Day</th>
                        <th scope="col">Requests</th>
                    </tr>
                </thead>
                <tbody>
                    {days.map((day) => (
                        <tr key={day.date}>
                            <td>{dateLabel(day.date, { weekday: 'long', month: 'long', day: 'numeric' })}</td>
                            <td>{day.count}</td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </figure>
    );
}

/** A round axis maximum divisible by 3: 3, 6, 9, 12, 15, 30 … */
export function niceMax(max: number): number {
    if (max <= 3) return 3;
    const step = max <= 15 ? 3 : max <= 60 ? 15 : 30;

    return Math.ceil(max / step) * step;
}

function dateLabel(date: string, options: Intl.DateTimeFormatOptions): string {
    return new Date(`${date}T12:00:00`).toLocaleDateString('en-US', options);
}

/** "What people ask about": share of requests per topic, longest first. */
export function TopicShare({ topics }: { topics: { value: string; label: string; count: number; percent: number }[] }) {
    return (
        <ul className="flex flex-col gap-4">
            {topics.map((topic) => (
                <li key={topic.value} className="flex flex-col gap-2">
                    <div className="flex items-baseline justify-between gap-3 text-admin-body text-ink">
                        <span className="min-w-0 truncate">{topic.label}</span>
                        <span className="shrink-0 font-semibold" title={`${topic.count} request${topic.count === 1 ? '' : 's'}`}>
                            {topic.percent}%
                        </span>
                    </div>
                    <span className="h-1.5 w-full overflow-hidden rounded-full bg-stone" aria-hidden="true">
                        <span className="block h-full rounded-full bg-royal" style={{ width: `${topic.percent}%` }} />
                    </span>
                </li>
            ))}
        </ul>
    );
}
