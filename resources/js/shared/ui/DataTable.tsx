import type { ReactNode } from 'react';
import { Fragment } from 'react';
import { ArrowDown, ArrowUp, ChevronsUpDown } from 'lucide-react';
import { cn } from './cn';
import { Skeleton } from './States';

/**
 * Server-driven table (guideline §14): it renders what the API returned and
 * reports sort clicks; filtering, sorting and paging happen in MySQL.
 */
export interface Column<T> {
    key: string;
    header: ReactNode;
    cell: (row: T) => ReactNode;
    /** Sort key sent to the API; omit for unsortable columns. */
    sortKey?: string;
    className?: string;
    align?: 'start' | 'center' | 'end';
}

export interface RowGroup<T> {
    label: string;
    rows: T[];
}

interface DataTableProps<T> {
    columns: Column<T>[];
    rows: T[] | RowGroup<T>[] | undefined;
    rowKey: (row: T) => string | number;
    caption: string;
    loading?: boolean;
    sort?: string | undefined;
    onSort?: (sort: string) => void;
    empty?: ReactNode;
    error?: ReactNode;
    skeletonRows?: number;
    rowClassName?: string | ((row: T) => string | undefined);
    /** Rendered inside the table's frame above the header, e.g. a bulk-action bar. */
    toolbar?: ReactNode;
}

const ALIGN = { start: 'text-left', center: 'text-center', end: 'text-right' } as const;

function isGrouped<T>(rows: T[] | RowGroup<T>[]): rows is RowGroup<T>[] {
    const first = rows[0] as unknown;

    return typeof first === 'object' && first !== null && 'label' in first && 'rows' in first && Array.isArray((first as RowGroup<T>).rows);
}

export function nextSort(current: string | undefined, key: string): string {
    if (current === key) {
        return `-${key}`;
    }

    return key;
}

export function DataTable<T>({ columns, rows, rowKey, caption, loading = false, sort, onSort, empty, error, skeletonRows = 5, rowClassName, toolbar }: DataTableProps<T>) {
    const groups: RowGroup<T>[] = rows === undefined ? [] : isGrouped(rows) ? rows : [{ label: '', rows }];
    const isEmpty = !loading && !error && groups.every((group) => group.rows.length === 0);

    return (
        <div className="overflow-hidden rounded-card border border-line bg-white">
            {toolbar}
            <div className="relative overflow-x-auto">
                <table className="w-full min-w-[720px] border-collapse text-admin-body" aria-busy={loading || undefined}>
                    <caption className="sr-only">{caption}</caption>
                    <thead>
                        <tr className="h-[52px] border-b border-line">
                            {columns.map((column) => {
                                const active = sort === column.sortKey || sort === `-${column.sortKey}`;
                                const descending = sort === `-${column.sortKey}`;

                                return (
                                    <th
                                        key={column.key}
                                        scope="col"
                                        aria-sort={active ? (descending ? 'descending' : 'ascending') : undefined}
                                        className={cn(
                                            'px-4 font-display text-[10px] leading-[1.3] font-bold tracking-[1.1px] whitespace-nowrap text-ink-soft uppercase first:pl-6 last:pr-6',
                                            ALIGN[column.align ?? 'start'],
                                            column.className,
                                        )}
                                    >
                                        {column.sortKey && onSort ? (
                                            <button type="button" onClick={() => onSort(nextSort(sort, column.sortKey as string))} className="inline-flex items-center gap-1 uppercase hover:text-ink">
                                                {column.header}
                                                {active ? (
                                                    descending ? <ArrowDown className="size-3" aria-hidden="true" /> : <ArrowUp className="size-3" aria-hidden="true" />
                                                ) : (
                                                    <ChevronsUpDown className="size-3 opacity-50" aria-hidden="true" />
                                                )}
                                            </button>
                                        ) : (
                                            column.header
                                        )}
                                    </th>
                                );
                            })}
                        </tr>
                    </thead>
                    <tbody>
                        {loading &&
                            Array.from({ length: skeletonRows }, (_, index) => (
                                <tr key={`skeleton-${index}`} className="h-[60px] border-b border-line last:border-b-0">
                                    {columns.map((column, columnIndex) => (
                                        <td key={column.key} className="px-4 first:pl-6 last:pr-6">
                                            <Skeleton className={cn('h-3', columnIndex === 0 ? 'w-40' : 'w-20')} />
                                        </td>
                                    ))}
                                </tr>
                            ))}
                        {!loading &&
                            !error &&
                            groups.map((group) => (
                                <Fragment key={group.label || 'all'}>
                                    {group.label && group.rows.length > 0 && (
                                        <tr className="bg-paper">
                                            <th colSpan={columns.length} scope="colgroup" className="px-6 py-2.5 text-left font-display text-[10px] leading-[1.3] font-bold tracking-[1.1px] text-ink-soft uppercase">
                                                {group.label}
                                            </th>
                                        </tr>
                                    )}
                                    {group.rows.map((row) => (
                                        <tr key={rowKey(row)} className={cn('h-[60px] border-b border-line last:border-b-0', typeof rowClassName === 'function' ? rowClassName(row) : rowClassName)}>
                                            {columns.map((column) => (
                                                <td key={column.key} className={cn('px-4 py-3 first:pl-6 last:pr-6', ALIGN[column.align ?? 'start'], column.className)}>
                                                    {column.cell(row)}
                                                </td>
                                            ))}
                                        </tr>
                                    ))}
                                </Fragment>
                            ))}
                    </tbody>
                </table>
            </div>
            {error}
            {isEmpty && empty}
        </div>
    );
}
