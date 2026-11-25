import { describe, expect, it } from 'vitest';
import { readTableParams, toQuery } from './useTableParams';

describe('table params', () => {
    it('reads the URL contract of docs/API.md §5', () => {
        const state = readTableParams(new URLSearchParams('search=joy&filter[status]=invited&filter[role]=&sort=-name&page=3&per_page=50'));

        expect(state).toEqual({ search: 'joy', filters: { status: 'invited' }, sort: '-name', page: 3, perPage: 50 });
        expect(toQuery(state)).toEqual({ search: 'joy', 'filter[status]': 'invited', sort: '-name', page: 3, per_page: 50 });
    });

    it('falls back on bad numbers and defaults', () => {
        const state = readTableParams(new URLSearchParams('page=-2&per_page=1000'), { sort: 'name', perPage: 10 });

        expect(state.page).toBe(1);
        expect(state.perPage).toBe(25);
        expect(state.sort).toBe('name');
    });
});
