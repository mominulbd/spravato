import { useCallback, useMemo } from 'react';
import { useSearchParams } from 'react-router';

/**
 * Table state lives in the URL (docs/API.md §5): `search`, `filter[x]`,
 * `sort`, `page`, `per_page`. Links are shareable and Back works. Changing a
 * filter or the search resets to page 1.
 */
export interface TableParams {
    search: string;
    filters: Record<string, string>;
    sort: string | undefined;
    page: number;
    perPage: number;
}

export function readTableParams(params: URLSearchParams, defaults: { sort?: string; perPage?: number } = {}): TableParams {
    const filters: Record<string, string> = {};

    params.forEach((value, key) => {
        const match = key.match(/^filter\[(.+)]$/);
        if (match?.[1] && value !== '') filters[match[1]] = value;
    });

    const page = Number(params.get('page') ?? 1);
    const perPage = Number(params.get('per_page') ?? defaults.perPage ?? 25);

    return {
        search: params.get('search') ?? '',
        filters,
        sort: params.get('sort') ?? defaults.sort,
        page: Number.isInteger(page) && page > 0 ? page : 1,
        perPage: Number.isInteger(perPage) && perPage > 0 && perPage <= 100 ? perPage : 25,
    };
}

/** The query object for api.get(). */
export function toQuery(state: TableParams): Record<string, string | number> {
    const query: Record<string, string | number> = { page: state.page, per_page: state.perPage };

    if (state.search !== '') query.search = state.search;
    if (state.sort) query.sort = state.sort;
    for (const [key, value] of Object.entries(state.filters)) query[`filter[${key}]`] = value;

    return query;
}

export function useTableParams(defaults: { sort?: string; perPage?: number } = {}) {
    const [params, setParams] = useSearchParams();
    const { sort: defaultSort, perPage: defaultPerPage } = defaults;
    const state = useMemo(() => readTableParams(params, { sort: defaultSort, perPage: defaultPerPage }), [params, defaultSort, defaultPerPage]);

    const update = useCallback(
        (changes: Record<string, string | number | null>, resetPage = true) => {
            setParams(
                (current) => {
                    const next = new URLSearchParams(current);
                    for (const [key, value] of Object.entries(changes)) {
                        if (value === null || value === '') next.delete(key);
                        else next.set(key, String(value));
                    }
                    if (resetPage && !('page' in changes)) next.delete('page');

                    return next;
                },
                { replace: true },
            );
        },
        [setParams],
    );

    return {
        ...state,
        query: toQuery(state),
        setSearch: (search: string) => update({ search }),
        setFilter: (key: string, value: string | null) => update({ [`filter[${key}]`]: value }),
        setSort: (sort: string) => update({ sort }),
        setPage: (page: number) => update({ page }, false),
        setPerPage: (perPage: number) => update({ per_page: perPage }),
    };
}
