import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';
import { DataTable, nextSort, type Column } from './DataTable';
import { pageWindow } from './Pagination';

interface Row {
    id: number;
    name: string;
}

const columns: Column<Row>[] = [
    { key: 'name', header: 'Name', sortKey: 'name', cell: (row) => row.name },
    { key: 'id', header: 'ID', cell: (row) => row.id },
];

describe('DataTable', () => {
    it('renders rows and reports sort clicks without sorting itself', async () => {
        const onSort = vi.fn();
        render(<DataTable caption="People" columns={columns} rows={[{ id: 2, name: 'Zed' }, { id: 1, name: 'Amy' }]} rowKey={(row) => row.id} sort="name" onSort={onSort} />);

        const rows = screen.getAllByRole('row');
        expect(within(rows[1] as HTMLElement).getByText('Zed')).toBeInTheDocument();
        expect(screen.getByRole('columnheader', { name: /name/i })).toHaveAttribute('aria-sort', 'ascending');

        await userEvent.click(screen.getByRole('button', { name: /name/i }));
        expect(onSort).toHaveBeenCalledWith('-name');
    });

    it('groups rows under headings', () => {
        render(<DataTable caption="Log" columns={columns} rows={[{ label: 'Today', rows: [{ id: 1, name: 'Amy' }] }, { label: 'Yesterday', rows: [{ id: 2, name: 'Bo' }] }]} rowKey={(row) => row.id} />);

        expect(screen.getByRole('columnheader', { name: 'Today' })).toHaveAttribute('scope', 'colgroup');
        expect(screen.getByRole('columnheader', { name: 'Yesterday' })).toBeInTheDocument();
    });

    it('shows the empty state only when loaded and empty', () => {
        const { rerender } = render(<DataTable caption="People" columns={columns} rows={[]} rowKey={(row) => row.id} loading empty={<p>Nobody yet</p>} />);
        expect(screen.queryByText('Nobody yet')).not.toBeInTheDocument();
        expect(screen.getByRole('table')).toHaveAttribute('aria-busy', 'true');

        rerender(<DataTable caption="People" columns={columns} rows={[]} rowKey={(row) => row.id} empty={<p>Nobody yet</p>} />);
        expect(screen.getByText('Nobody yet')).toBeInTheDocument();
    });

    it('cycles sort ascending → descending', () => {
        expect(nextSort(undefined, 'name')).toBe('name');
        expect(nextSort('name', 'name')).toBe('-name');
        expect(nextSort('-name', 'name')).toBe('name');
        expect(nextSort('-email', 'name')).toBe('name');
    });
});

describe('pageWindow', () => {
    it('collapses long ranges like the Figma pager', () => {
        expect(pageWindow(1, 5)).toEqual([1, 2, 3, 4, 5]);
        expect(pageWindow(1, 13)).toEqual([1, 2, 3, 4, 'gap', 13]);
        expect(pageWindow(7, 13)).toEqual([1, 'gap', 6, 7, 8, 'gap', 13]);
        expect(pageWindow(13, 13)).toEqual([1, 'gap', 10, 11, 12, 13]);
    });
});
