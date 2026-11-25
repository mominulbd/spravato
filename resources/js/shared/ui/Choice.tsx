import type { InputHTMLAttributes, ReactNode } from 'react';
import { forwardRef, useId } from 'react';
import { Check } from 'lucide-react';
import { cn } from './cn';

/** Selection controls (Figma "Selection controls"): native inputs, custom paint. */
interface ChoiceProps extends Omit<InputHTMLAttributes<HTMLInputElement>, 'type'> {
    label: ReactNode;
    description?: ReactNode;
}

export const Checkbox = forwardRef<HTMLInputElement, ChoiceProps>(function Checkbox({ label, description, className, ...props }, ref) {
    const id = useId();

    return (
        <label htmlFor={id} className={cn('inline-flex cursor-pointer items-start gap-2.5', props.disabled && 'cursor-not-allowed opacity-60', className)}>
            <span className="relative mt-0.5 inline-flex size-[18px] shrink-0">
                <input ref={ref} id={id} type="checkbox" className="peer absolute inset-0 m-0 cursor-[inherit] opacity-0" {...props} />
                <span className="pointer-events-none inline-flex size-[18px] items-center justify-center rounded-[5px] border border-line bg-white text-white transition-colors peer-checked:border-royal peer-checked:bg-royal peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-royal [&>svg]:opacity-0 peer-checked:[&>svg]:opacity-100">
                    <Check className="size-3.5" strokeWidth={3} aria-hidden="true" />
                </span>
            </span>
            <span className="flex flex-col">
                <span className="text-admin-body text-ink">{label}</span>
                {description && <span className="text-admin-small text-ink-soft">{description}</span>}
            </span>
        </label>
    );
});

export const Radio = forwardRef<HTMLInputElement, ChoiceProps>(function Radio({ label, description, className, ...props }, ref) {
    const id = useId();

    return (
        <label htmlFor={id} className={cn('inline-flex cursor-pointer items-start gap-2.5', props.disabled && 'cursor-not-allowed opacity-60', className)}>
            <span className="relative mt-0.5 inline-flex size-[18px] shrink-0">
                <input ref={ref} id={id} type="radio" className="peer absolute inset-0 m-0 cursor-[inherit] opacity-0" {...props} />
                <span className="pointer-events-none inline-flex size-[18px] items-center justify-center rounded-full border border-line bg-white transition-colors peer-checked:border-royal peer-checked:[&>span]:scale-100 peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-royal">
                    <span className="size-2 scale-0 rounded-full bg-royal transition-transform" />
                </span>
            </span>
            <span className="flex flex-col">
                <span className="text-admin-body text-ink">{label}</span>
                {description && <span className="text-admin-small text-ink-soft">{description}</span>}
            </span>
        </label>
    );
});

export const Toggle = forwardRef<HTMLInputElement, ChoiceProps>(function Toggle({ label, description, className, ...props }, ref) {
    const id = useId();

    return (
        <label htmlFor={id} className={cn('inline-flex cursor-pointer items-start gap-2.5', props.disabled && 'cursor-not-allowed opacity-60', className)}>
            <span className="relative mt-px inline-flex h-5 w-9 shrink-0">
                <input ref={ref} id={id} type="checkbox" role="switch" className="peer absolute inset-0 m-0 cursor-[inherit] opacity-0" {...props} />
                <span className="pointer-events-none h-5 w-9 rounded-full bg-stone transition-colors peer-checked:bg-royal peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-royal" />
                <span className="pointer-events-none absolute top-0.5 left-0.5 size-4 rounded-full bg-white shadow-sm transition-transform peer-checked:translate-x-4" />
            </span>
            <span className="flex flex-col">
                <span className="text-admin-body text-ink">{label}</span>
                {description && <span className="text-admin-small text-ink-soft">{description}</span>}
            </span>
        </label>
    );
});
