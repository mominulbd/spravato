import logo from '../../../images/uptms-logo.png';
import { cn } from '@shared/ui';

/** The clinic logo on its white plate (Figma "Logo plate"). */
export function Logo({ size = 'md', className }: { size?: 'md' | 'lg'; className?: string }) {
    return (
        <span className={cn('inline-flex w-fit shrink-0 rounded-control bg-white', size === 'md' ? 'px-2.5 py-1.5' : 'px-3 py-2', className)}>
            <img src={logo} alt="University Place TMS Clinic" className={size === 'md' ? 'h-10 w-[140px] object-contain' : 'h-12 w-[168px] object-contain'} />
        </span>
    );
}
