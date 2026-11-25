import { ChevronDown, ChevronLeft, ChevronRight } from 'lucide-react';
import { cn } from './cn';

/** Figma "Data display": "Showing 1–10 of 128", rows per page, numbered pages. */
export interface PageMeta {
    current_page: number;
    per_page: number;
    total: number;
    last_page: number;
    from: number | null;
    to: number | null;
}

/** Page numbers with gaps: 1 … 4 5 6 … 13. */
export function pageWindow(current: number, last: number): (number | 'gap')[] {
    if (last <= 7) {
        return Array.from({ length: last }, (_, i) => i + 1);
    }

    const pages = new Set([1, last, current - 1, current, current + 1]);
    if (current <= 3) [2, 3, 4].forEach((page) => pages.add(page));
    if (current >= last - 2) [last - 3, last - 2, last - 1].forEach((page) => pages.add(page));

    const sorted = [...pages].filter((page) => page >= 1 && page <= last).sort((a, b) => a - b);
    const result: (number | 'gap')[] = [];

    sorted.forEach((page, index) => {
        const previous = sorted[index - 1];
        if (previous !== undefined && page - previous > 1) result.push('gap');
        result.push(page);
    });

    return result;
}

const pageButton = 'inline-flex size-[34px] items-center justify-center rounded-[10px] border text-admin-body font-semibold transition-colors';

export function Pagination({ meta, onPage, onPerPage, noun = 'entries' }: { meta: PageMeta; onPage: (page: number) => void; onPerPage?: (perPage: number) => void; noun?: string }) {
    if (meta.total === 0) {
        return null;
    }

    return (
        <div className="flex flex-wrap items-center justify-between gap-4">
            <p className="text-admin-small text-ink-soft">
                Showing {meta.from}–{meta.to} of {meta.total.toLocaleString()} {noun}
            </p>
            <div className="flex flex-wrap items-center gap-6">
                {onPerPage && (
                    <label className="flex items-center gap-2.5 text-admin-small text-ink-soft">
                        Rows
                        <span className="relative">
                            <select
                                value={meta.per_page}
                                onChange={(event) => onPerPage(Number(event.target.value))}
                                className="h-[34px] appearance-none rounded-[10px] border border-line bg-white pr-8 pl-3 text-admin-body text-ink"
                            >
                                {[10, 25, 50, 100].map((size) => (
                                    <option key={size} value={size}>
                                        {size}
                                    </option>
                                ))}
                            </select>
                            <ChevronDown className="pointer-events-none absolute top-1/2 right-2.5 size-3.5 -translate-y-1/2" aria-hidden="true" />
                        </span>
                    </label>
                )}
                {meta.last_page > 1 && (
                    <nav aria-label="Pagination" className="flex items-center gap-2">
                        <button type="button" aria-label="Previous page" disabled={meta.current_page <= 1} onClick={() => onPage(meta.current_page - 1)} className={cn(pageButton, 'border-line bg-white text-ink disabled:opacity-40')}>
                            <ChevronLeft className="size-4" aria-hidden="true" />
                        </button>
                        {pageWindow(meta.current_page, meta.last_page).map((page, index) =>
                            page === 'gap' ? (
                                <span key={`gap-${index}`} className={cn(pageButton, 'border-line bg-white text-ink-soft')} aria-hidden="true">
                                    …
                                </span>
                            ) : (
                                <button
                                    key={page}
                                    type="button"
                                    aria-current={page === meta.current_page ? 'page' : undefined}
                                    onClick={() => onPage(page)}
                                    className={cn(pageButton, page === meta.current_page ? 'border-navy bg-navy text-white' : 'border-line bg-white text-ink hover:border-ink-soft')}
                                >
                                    {page}
                                </button>
                            ),
                        )}
                        <button type="button" aria-label="Next page" disabled={meta.current_page >= meta.last_page} onClick={() => onPage(meta.current_page + 1)} className={cn(pageButton, 'border-line bg-white text-ink disabled:opacity-40')}>
                            <ChevronRight className="size-4" aria-hidden="true" />
                        </button>
                    </nav>
                )}
            </div>
        </div>
    );
}
