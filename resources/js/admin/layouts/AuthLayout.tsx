import type { ReactNode } from 'react';
import { Check } from 'lucide-react';
import { Logo } from '@admin/components/Logo';

/**
 * Sign-in frames. `split` is Figma 01 (brand panel + card); `centered` is
 * Figma 02 (logo above a card), used by every other sign-in step.
 */
export function AuthLayout({ variant = 'centered', children }: { variant?: 'split' | 'centered'; children: ReactNode }) {
    if (variant === 'centered') {
        return (
            <main className="flex min-h-dvh flex-col items-center justify-center gap-6 bg-canvas px-4 py-10">
                <Logo size="lg" className="self-center border border-line" />
                <div className="w-full max-w-[440px]">{children}</div>
            </main>
        );
    }

    return (
        <div className="flex min-h-dvh gap-6 bg-canvas p-3 sm:p-6">
            <section aria-label="About this console" className="hidden min-h-full flex-1 flex-col justify-between rounded-panel bg-navy px-16 py-14 lg:flex">
                <Logo size="lg" className="self-start" />
                <div className="flex max-w-[520px] flex-col gap-5">
                    <p className="font-display text-admin-small font-bold tracking-[1.1px] text-sky uppercase">Content &amp; SEO console</p>
                    <p className="font-serif text-[56px] leading-[1.06] tracking-[-2px] text-white">
                        Keep every page as caring as <em className="text-sky">the clinic.</em>
                    </p>
                    <p className="max-w-[480px] text-[17px] leading-[1.6] text-white">
                        Edit pages, publish patient resources, answer consultation requests and keep uptms.com search-ready — all in one place.
                    </p>
                    <ul className="flex flex-col gap-2.5">
                        {['Role-based access for every team member', 'Encrypted consultation inbox', 'Built-in SEO checks before you publish'].map((line) => (
                            <li key={line} className="flex items-center gap-2.5 text-admin-body text-white">
                                <Check className="size-[18px] text-sky" aria-hidden="true" />
                                {line}
                            </li>
                        ))}
                    </ul>
                </div>
                <p className="text-admin-small text-white">© {new Date().getFullYear()} University Place TMS Clinic &amp; General Psychiatry</p>
            </section>
            <main className="flex w-full flex-col items-center justify-center gap-6 py-6 lg:w-[560px] lg:shrink-0">
                <Logo size="lg" className="self-center border border-line lg:hidden" />
                <div className="w-full max-w-[440px]">{children}</div>
            </main>
        </div>
    );
}

/** The white sign-in card (Figma 55:213). */
export function AuthCard({ title, description, children }: { title: string; description?: ReactNode; children: ReactNode }) {
    return (
        <div className="flex w-full flex-col gap-5 rounded-[20px] border border-line bg-white p-7 shadow-[0_16px_20px_rgba(14,48,78,0.08)] sm:p-10">
            <div className="flex flex-col gap-3">
                <h1 className="font-serif text-admin-page-title text-ink">{title}</h1>
                {description && <div className="text-admin-body text-ink-soft">{description}</div>}
            </div>
            {children}
        </div>
    );
}
