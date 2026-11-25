import type { InputHTMLAttributes, ReactNode, SelectHTMLAttributes, TextareaHTMLAttributes } from 'react';
import { forwardRef, useId } from 'react';
import { ChevronDown, Search } from 'lucide-react';
import { cn } from './cn';

/**
 * Admin/Input, Select, Textarea (Figma 53:111). The label, help and error
 * text are wired with aria-describedby / aria-invalid (guideline §24).
 */
interface FieldProps {
    label?: ReactNode;
    help?: ReactNode;
    error?: string | undefined;
    className?: string;
    children: (props: { id: string; describedBy: string | undefined; invalid: boolean }) => ReactNode;
    /** Shown at the right of the help line, e.g. a "0 / 160" counter. */
    aside?: ReactNode;
}

export function Field({ label, help, error, className, children, aside }: FieldProps) {
    const id = useId();
    const helpId = `${id}-help`;
    const errorId = `${id}-error`;
    const describedBy = [error ? errorId : null, help ? helpId : null].filter(Boolean).join(' ') || undefined;

    return (
        <div className={cn('flex w-full flex-col gap-1.5', className)}>
            {label !== undefined && (
                <label htmlFor={id} className="text-admin-label font-semibold text-ink">
                    {label}
                </label>
            )}
            {children({ id, describedBy, invalid: Boolean(error) })}
            {error && (
                <p id={errorId} className="text-admin-small text-danger">
                    {error}
                </p>
            )}
            {(help || aside) && (
                <div className="flex items-start justify-between gap-3 text-admin-small text-ink-soft">
                    {help ? <p id={helpId}>{help}</p> : <span />}
                    {aside}
                </div>
            )}
        </div>
    );
}

export const controlClasses = (invalid: boolean, className?: string) =>
    cn(
        'h-11 w-full min-w-0 rounded-control border bg-white px-3.5 text-admin-body text-ink placeholder:text-ink-soft/70',
        'transition-colors outline-none focus-visible:outline-none disabled:cursor-not-allowed disabled:bg-paper disabled:text-ink-soft',
        invalid ? 'border-danger focus:ring-2 focus:ring-danger/25' : 'border-line hover:border-ink-soft/50 focus:border-royal focus:ring-2 focus:ring-royal/20',
        className,
    );

type InputProps = InputHTMLAttributes<HTMLInputElement> & Omit<FieldProps, 'children'>;

export const TextInput = forwardRef<HTMLInputElement, InputProps>(function TextInput(
    { label, help, error, className, aside, ...props },
    ref,
) {
    return (
        <Field label={label} help={help} error={error} className={className} aside={aside}>
            {({ id, describedBy, invalid }) => (
                <input ref={ref} id={id} aria-describedby={describedBy} aria-invalid={invalid || undefined} className={controlClasses(invalid)} {...props} />
            )}
        </Field>
    );
});

type SelectProps = SelectHTMLAttributes<HTMLSelectElement> & Omit<FieldProps, 'children'>;

export const Select = forwardRef<HTMLSelectElement, SelectProps>(function Select(
    { label, help, error, className, aside, children, ...props },
    ref,
) {
    return (
        <Field label={label} help={help} error={error} className={className} aside={aside}>
            {({ id, describedBy, invalid }) => (
                <div className="relative">
                    <select
                        ref={ref}
                        id={id}
                        aria-describedby={describedBy}
                        aria-invalid={invalid || undefined}
                        className={controlClasses(invalid, 'appearance-none pr-10')}
                        {...props}
                    >
                        {children}
                    </select>
                    <ChevronDown aria-hidden="true" className="pointer-events-none absolute top-1/2 right-3.5 size-4 -translate-y-1/2 text-ink-soft" />
                </div>
            )}
        </Field>
    );
});

type TextareaProps = TextareaHTMLAttributes<HTMLTextAreaElement> & Omit<FieldProps, 'children'>;

export const Textarea = forwardRef<HTMLTextAreaElement, TextareaProps>(function Textarea(
    { label, help, error, className, maxLength, value, aside, ...props },
    ref,
) {
    const counter = maxLength !== undefined && typeof value === 'string' ? `${value.length} / ${maxLength}` : undefined;

    return (
        <Field label={label} help={help} error={error} className={className} aside={aside ?? counter}>
            {({ id, describedBy, invalid }) => (
                <textarea
                    ref={ref}
                    id={id}
                    aria-describedby={describedBy}
                    aria-invalid={invalid || undefined}
                    maxLength={maxLength}
                    value={value}
                    className={controlClasses(invalid, 'h-auto min-h-24 py-2.5')}
                    {...props}
                />
            )}
        </Field>
    );
});

interface SearchInputProps extends Omit<InputHTMLAttributes<HTMLInputElement>, 'type'> {
    label: string;
}

/** Search box with a magnifier; `label` is visually hidden. */
export const SearchInput = forwardRef<HTMLInputElement, SearchInputProps>(function SearchInput({ label, className, ...props }, ref) {
    return (
        <div className={cn('relative', className)}>
            <Search aria-hidden="true" className="pointer-events-none absolute top-1/2 left-4 size-[18px] -translate-y-1/2 text-ink-soft" />
            <input ref={ref} type="search" aria-label={label} className={controlClasses(false, 'rounded-full pl-11')} {...props} />
        </div>
    );
});
