import { cn } from './cn';
import { initials } from '../auth/can';

const SIZES = { sm: 'size-7 text-[11px]', md: 'size-9 text-[13px]', lg: 'size-14 text-[18px]' } as const;

/** Admin/Avatar (Figma 53:213): initials on sky. */
export function Avatar({ name, size = 'md', className }: { name: string; size?: keyof typeof SIZES; className?: string }) {
    return (
        <span aria-hidden="true" className={cn('inline-flex shrink-0 items-center justify-center rounded-full bg-sky font-display font-bold text-navy', SIZES[size], className)}>
            {initials(name)}
        </span>
    );
}
