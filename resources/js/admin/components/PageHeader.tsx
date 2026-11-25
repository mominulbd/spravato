import type { ReactNode } from 'react';

/**
 * Page header with rail (Figma screens 25–27): breadcrumb overline, serif
 * title whose last word is set in royal italic, description, actions.
 */
interface PageHeaderProps {
    trail: string[];
    title: string;
    accent?: string;
    description?: ReactNode;
    actions?: ReactNode;
}

export function PageHeader({ trail, title, accent, description, actions }: PageHeaderProps) {
    return (
        <header className="flex w-full flex-col gap-3">
            <p className="flex items-center gap-2.5 font-display text-admin-overline font-bold tracking-[1.1px] text-ink-soft uppercase">
                <span aria-hidden="true" className="h-[1.5px] w-7 bg-royal" />
                <span className="sr-only">You are in: </span>
                {trail.join('  /  ')}
            </p>
            <div className="flex flex-wrap items-end justify-between gap-x-8 gap-y-4">
                <div className="flex min-w-0 max-w-[640px] flex-col gap-2">
                    <h1 className="font-serif text-admin-hero text-ink">
                        {title}
                        {accent && (
                            <>
                                {' '}
                                <em className="text-royal">{accent}</em>
                            </>
                        )}
                    </h1>
                    {description && <p className="text-[15px] leading-[1.5] text-ink-soft">{description}</p>}
                </div>
                {actions && <div className="flex flex-wrap items-center gap-3">{actions}</div>}
            </div>
        </header>
    );
}
