import type { KeyboardEvent } from 'react';
import { useState } from 'react';
import { Plus, X } from 'lucide-react';
import { Field } from './Field';
import { cn } from './cn';

/**
 * Removable chips with an "Add" field (Figma 07 trust points, 14
 * highlights, 24 service area). Enter or comma adds; duplicates are ignored.
 */
interface TagInputProps {
    label: string;
    value: string[];
    onChange: (value: string[]) => void;
    help?: string;
    error?: string;
    max?: number;
    maxLength?: number;
    placeholder?: string;
}

export function TagInput({ label, value, onChange, help, error, max = 12, maxLength = 60, placeholder = 'Add an item' }: TagInputProps) {
    const [draft, setDraft] = useState('');
    const [adding, setAdding] = useState(false);
    const full = value.length >= max;

    const add = () => {
        const text = draft.trim().slice(0, maxLength);

        if (text !== '' && !value.includes(text) && !full) {
            onChange([...value, text]);
        }

        setDraft('');
    };

    const onKeyDown = (event: KeyboardEvent<HTMLInputElement>) => {
        if (event.key === 'Enter' || event.key === ',') {
            event.preventDefault();
            add();
        } else if (event.key === 'Escape') {
            setDraft('');
            setAdding(false);
        }
    };

    return (
        <Field label={label} help={help} error={error} aside={`${value.length} / ${max}`}>
            {({ id, describedBy }) => (
                <div className="flex flex-wrap items-center gap-2" aria-describedby={describedBy}>
                    {value.map((tag) => (
                        <span key={tag} className="inline-flex max-w-full items-center gap-1.5 rounded-full border border-line bg-white py-1.5 pr-2 pl-3.5 text-admin-body text-ink">
                            <span className="min-w-0 truncate">{tag}</span>
                            <button
                                type="button"
                                aria-label={`Remove ${tag}`}
                                onClick={() => onChange(value.filter((item) => item !== tag))}
                                className="rounded-full p-0.5 text-ink-soft hover:bg-stone hover:text-ink"
                            >
                                <X className="size-3.5" aria-hidden="true" />
                            </button>
                        </span>
                    ))}
                    {adding ? (
                        <input
                            id={id}
                            autoFocus
                            value={draft}
                            maxLength={maxLength}
                            placeholder={placeholder}
                            onChange={(event) => setDraft(event.target.value)}
                            onKeyDown={onKeyDown}
                            onBlur={() => {
                                add();
                                setAdding(false);
                            }}
                            className="h-9 min-w-40 flex-1 rounded-full border border-royal bg-white px-3.5 text-admin-body outline-none focus:ring-2 focus:ring-royal/20"
                        />
                    ) : (
                        <button
                            id={id}
                            type="button"
                            disabled={full}
                            onClick={() => setAdding(true)}
                            className={cn(
                                'inline-flex items-center gap-1.5 rounded-full bg-sky-soft px-3.5 py-1.5 text-admin-body font-semibold text-royal hover:bg-sky',
                                full && 'cursor-not-allowed opacity-50',
                            )}
                        >
                            <Plus className="size-3.5" aria-hidden="true" /> Add
                        </button>
                    )}
                </div>
            )}
        </Field>
    );
}
