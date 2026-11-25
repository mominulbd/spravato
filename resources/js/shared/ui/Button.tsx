import type { ButtonHTMLAttributes, ReactNode } from 'react';
import { forwardRef } from 'react';
import { Link, type LinkProps } from 'react-router';
import { cn } from './cn';
import { Spinner } from './Spinner';

/**
 * Admin/Button (Figma 53:65). Primary = the main action of a view, Danger =
 * destructive (always behind a ConfirmDialog).
 */
export type ButtonVariant = 'primary' | 'secondary' | 'ghost' | 'danger' | 'soft' | 'navy';
export type ButtonSize = 'md' | 'sm';

const VARIANTS: Record<ButtonVariant, string> = {
    primary: 'bg-royal text-white hover:bg-royal-deep',
    secondary: 'border border-line bg-white text-ink hover:border-ink-soft',
    ghost: 'text-ink hover:bg-stone/60',
    danger: 'bg-danger text-white hover:bg-[#8f1c13]',
    soft: 'bg-sky-soft text-royal hover:bg-sky',
    navy: 'bg-navy text-white hover:bg-sidebar-active',
};

const SIZES: Record<ButtonSize, string> = {
    md: 'h-10 gap-2 px-[18px] text-[14px]',
    sm: 'h-8 gap-1.5 px-[14px] text-[13px]',
};

export function buttonClasses(variant: ButtonVariant = 'primary', size: ButtonSize = 'md', className?: string): string {
    return cn(
        'inline-flex shrink-0 items-center justify-center rounded-full font-display font-bold leading-[1.2] whitespace-nowrap transition-colors',
        'disabled:cursor-not-allowed disabled:opacity-50 aria-disabled:cursor-not-allowed aria-disabled:opacity-50',
        VARIANTS[variant],
        SIZES[size],
        className,
    );
}

interface ButtonProps extends ButtonHTMLAttributes<HTMLButtonElement> {
    variant?: ButtonVariant;
    size?: ButtonSize;
    icon?: ReactNode;
    loading?: boolean;
}

export const Button = forwardRef<HTMLButtonElement, ButtonProps>(function Button(
    { variant = 'primary', size = 'md', icon, loading = false, className, children, disabled, type = 'button', ...props },
    ref,
) {
    return (
        <button
            ref={ref}
            type={type}
            className={buttonClasses(variant, size, className)}
            disabled={disabled || loading}
            aria-busy={loading || undefined}
            {...props}
        >
            {loading ? <Spinner className="size-4" /> : icon}
            {children}
        </button>
    );
});

interface ButtonLinkProps extends LinkProps {
    variant?: ButtonVariant;
    size?: ButtonSize;
    icon?: ReactNode;
}

export function ButtonLink({ variant = 'primary', size = 'md', icon, className, children, ...props }: ButtonLinkProps) {
    return (
        <Link className={buttonClasses(variant, size, typeof className === 'string' ? className : undefined)} {...props}>
            {icon}
            {children}
        </Link>
    );
}

interface IconButtonProps extends ButtonHTMLAttributes<HTMLButtonElement> {
    label: string;
    children: ReactNode;
    tone?: 'light' | 'dark';
}

/** Round 40px icon button (top bar). `label` is the accessible name. */
export const IconButton = forwardRef<HTMLButtonElement, IconButtonProps>(function IconButton(
    { label, children, tone = 'light', className, type = 'button', ...props },
    ref,
) {
    return (
        <button
            ref={ref}
            type={type}
            aria-label={label}
            title={label}
            className={cn(
                'relative inline-flex size-10 shrink-0 items-center justify-center rounded-full transition-colors',
                tone === 'light' ? 'border border-line bg-white text-ink hover:border-ink-soft' : 'text-white/80 hover:bg-white/10 hover:text-white',
                className,
            )}
            {...props}
        >
            {children}
        </button>
    );
});
