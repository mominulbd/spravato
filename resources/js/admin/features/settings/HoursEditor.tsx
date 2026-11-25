import { CalendarPlus, Trash2 } from 'lucide-react';
import { Button, Toggle, cn } from '@shared/ui';
import type { Closure, HoursDay, HoursSettings } from './types';

/** "8:00 AM – 5:00 PM" */
export function displayTime(value: string | null): string {
    if (!value) return '';
    const [h = 0, m = 0] = value.split(':').map(Number);
    const suffix = h >= 12 ? 'PM' : 'AM';

    return `${((h + 11) % 12) + 1}:${String(m).padStart(2, '0')} ${suffix}`;
}

interface WeekProps {
    hours: HoursSettings;
    errors: Record<string, string>;
    onChange: (hours: HoursSettings) => void;
    compact?: boolean;
}

/** Office hours, one row per day (Figma 24 right column). */
export function WeekEditor({ hours, errors, onChange, compact = false }: WeekProps) {
    const setDay = (index: number, patch: Partial<HoursDay>) => onChange({ ...hours, days: hours.days.map((day, i) => (i === index ? { ...day, ...patch } : day)) });

    return (
        <ul className="flex flex-col">
            {hours.days.map((day, index) => {
                const error = errors[`days.${index}.closes_at`] ?? errors[`days.${index}.opens_at`];

                return (
                    <li key={day.day} className="flex flex-col gap-1 border-t border-line py-2.5 first:border-t-0">
                        <div className="flex min-h-9 flex-wrap items-center justify-between gap-x-4 gap-y-2">
                            <Toggle
                                label={<span className="inline-block min-w-[84px] font-semibold">{day.name}</span>}
                                checked={day.is_open}
                                onChange={(event) =>
                                    setDay(index, event.target.checked ? { is_open: true, opens_at: day.opens_at ?? '08:00', closes_at: day.closes_at ?? '17:00' } : { is_open: false })
                                }
                            />
                            {day.is_open && compact ? (
                                <span className={cn('text-admin-body', error ? 'text-danger' : 'text-ink')}>
                                    {displayTime(day.opens_at)} – {displayTime(day.closes_at)}
                                </span>
                            ) : day.is_open ? (
                                <span className="flex items-center gap-2">
                                    <TimeInput label={`${day.name} opens`} value={day.opens_at} invalid={Boolean(error)} onChange={(value) => setDay(index, { opens_at: value })} compact={compact} />
                                    <span aria-hidden="true" className="text-ink-soft">–</span>
                                    <TimeInput label={`${day.name} closes`} value={day.closes_at} invalid={Boolean(error)} onChange={(value) => setDay(index, { closes_at: value })} compact={compact} />
                                </span>
                            ) : (
                                <span className="text-admin-body text-ink-soft">Closed</span>
                            )}
                        </div>
                        {error && <p className="text-right text-admin-small text-danger">{error}</p>}
                    </li>
                );
            })}
        </ul>
    );
}

function TimeInput({ label, value, invalid, onChange, compact }: { label: string; value: string | null; invalid: boolean; onChange: (value: string) => void; compact: boolean }) {
    return (
        <input
            type="time"
            aria-label={label}
            aria-invalid={invalid || undefined}
            value={value ?? ''}
            onChange={(event) => onChange(event.target.value)}
            className={cn(
                'rounded-[10px] border bg-white px-2.5 text-admin-body text-ink outline-none focus:border-royal focus:ring-2 focus:ring-royal/20',
                compact ? 'h-9 w-[118px]' : 'h-10 w-[130px]',
                invalid ? 'border-danger' : 'border-line',
            )}
        />
    );
}

/** Holiday and one-off closures. */
export function ClosuresEditor({ closures, errors, onChange }: { closures: Closure[]; errors: Record<string, string>; onChange: (closures: Closure[]) => void }) {
    const set = (index: number, patch: Partial<Closure>) => onChange(closures.map((closure, i) => (i === index ? { ...closure, ...patch } : closure)));

    return (
        <div className="flex flex-col gap-3">
            {closures.length === 0 && <p className="rounded-card bg-paper px-4 py-3 text-admin-body text-ink-soft">No closures planned. The website shows upcoming ones beside your hours.</p>}
            {closures.map((closure, index) => (
                <div key={index} className="grid grid-cols-[160px_minmax(0,1fr)_auto] items-start gap-3">
                    <div className="flex flex-col gap-1">
                        <input
                            type="date"
                            aria-label={`Closure ${index + 1} date`}
                            value={closure.date}
                            onChange={(event) => set(index, { date: event.target.value })}
                            className={cn('h-11 rounded-control border bg-white px-3 text-admin-body outline-none focus:border-royal focus:ring-2 focus:ring-royal/20', errors[`closures.${index}.date`] ? 'border-danger' : 'border-line')}
                        />
                        {errors[`closures.${index}.date`] && <span className="text-admin-small text-danger">{errors[`closures.${index}.date`]}</span>}
                    </div>
                    <div className="flex flex-col gap-1">
                        <input
                            aria-label={`Closure ${index + 1} name`}
                            value={closure.label}
                            maxLength={80}
                            placeholder="e.g. Thanksgiving"
                            onChange={(event) => set(index, { label: event.target.value })}
                            className={cn('h-11 rounded-control border bg-white px-3.5 text-admin-body outline-none focus:border-royal focus:ring-2 focus:ring-royal/20', errors[`closures.${index}.label`] ? 'border-danger' : 'border-line')}
                        />
                        {errors[`closures.${index}.label`] && <span className="text-admin-small text-danger">{errors[`closures.${index}.label`]}</span>}
                    </div>
                    <button type="button" aria-label={`Remove closure ${closure.label || index + 1}`} onClick={() => onChange(closures.filter((_, i) => i !== index))} className="mt-1 rounded-full p-2.5 text-ink-soft hover:bg-danger-soft hover:text-danger">
                        <Trash2 className="size-4" aria-hidden="true" />
                    </button>
                </div>
            ))}
            <Button variant="soft" size="sm" className="self-start" icon={<CalendarPlus className="size-4" />} disabled={closures.length >= 60} onClick={() => onChange([...closures, { date: '', label: '' }])}>
                Add a holiday closure
            </Button>
        </div>
    );
}
