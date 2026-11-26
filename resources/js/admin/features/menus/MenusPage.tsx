import type { ReactNode } from 'react';
import { useEffect, useState } from 'react';
import { useBlocker, useSearchParams } from 'react-router';
import { ArrowLeftToLine, ArrowRightToLine, Check, CornerDownRight, Ellipsis, Eye, EyeOff, Menu as MenuIcon, Pencil, Trash2 } from 'lucide-react';
import { ApiError } from '@shared/api/client';
import { can } from '@shared/auth/can';
import { Button, ConfirmDialog, Dropdown, EmptyState, ErrorState, Skeleton, SortableList, cn, moveItem, useToast } from '@shared/ui';
import { useCurrentUser } from '@admin/app/AuthProvider';
import { PageHeader } from '@admin/components/PageHeader';
import { FormAlert } from '@admin/features/auth/FormAlert';
import { AddItemsPanel } from './AddItemsPanel';
import { useMenu, useMenus, usePageOptions, useSaveMenu } from './api';
import { ItemDialog } from './ItemDialog';
import { countItems, documentMissing, fromServer, hrefOf, indent, moveChild, outdent, pageMissing, removeItem, toPayload, updateItem } from './menuTree';
import type { EditItem, MenuDetail, PageOption } from './types';

const TYPE_LABEL = { page: 'Page', anchor: 'Anchor', link: 'Link', document: 'Document' } as const;

/** Figma 17: pick a menu, reorder and edit its links, add pages or links. */
export function MenusPage() {
    const [params, setParams] = useSearchParams();
    const menus = useMenus();
    const key = params.get('menu') ?? menus.data?.[0]?.key ?? null;
    const menu = useMenu(key);
    const pages = usePageOptions();

    return (
        <div className="flex flex-col gap-7">
            {menus.isError ? (
                <ErrorState title="Menus didn’t load" description="Check your connection and try again." onRetry={() => void menus.refetch()} />
            ) : menu.data && key ? (
                <Editor
                    key={key}
                    menu={menu.data}
                    pages={pages.data ?? []}
                    pagesLoading={pages.isPending}
                    menuList={<MenuList selected={key} onSelect={(next) => setParams({ menu: next })} />}
                />
            ) : (
                <>
                    <Header actions={null} />
                    <div className="grid gap-6 lg:grid-cols-[300px_minmax(0,1fr)]">
                        <Skeleton className="h-80 w-full rounded-sheet" />
                        <Skeleton className="h-96 w-full rounded-sheet" />
                        <Skeleton className="h-80 w-full rounded-sheet" />
                    </div>
                </>
            )}
        </div>
    );
}

function Header({ actions }: { actions: ReactNode }) {
    return (
        <PageHeader
            trail={['Content', 'Navigation menus']}
            title="Navigation"
            accent="menus."
            description="Drag to reorder. Menu links to pages update automatically when a page’s address changes."
            actions={actions}
        />
    );
}

function MenuList({ selected, onSelect }: { selected: string; onSelect: (key: string) => void }) {
    const menus = useMenus();

    return (
        <nav aria-label="Menus" className="flex flex-col gap-1 rounded-sheet border border-line bg-white p-3">
            {(menus.data ?? []).map((menu) => (
                <button
                    key={menu.key}
                    type="button"
                    aria-current={menu.key === selected ? 'true' : undefined}
                    onClick={() => onSelect(menu.key)}
                    className={cn('flex flex-col rounded-card px-3.5 py-3 text-left transition-colors', menu.key === selected ? 'bg-sky-soft' : 'hover:bg-paper')}
                >
                    <span className={cn('text-[15px] font-semibold', menu.key === selected ? 'text-royal' : 'text-ink')}>{menu.name}</span>
                    <span className="text-admin-small text-ink-soft">
                        {menu.items_count} {menu.key === 'section-nav' ? (menu.items_count === 1 ? 'anchor' : 'anchors') : menu.items_count === 1 ? 'item' : 'items'}
                    </span>
                </button>
            ))}
        </nav>
    );
}

function Editor({ menu, pages, pagesLoading, menuList }: { menu: MenuDetail; pages: PageOption[]; pagesLoading: boolean; menuList: ReactNode }) {
    const user = useCurrentUser();
    const toast = useToast();
    const save = useSaveMenu(menu.key);
    const [items, setItems] = useState<EditItem[]>(() => fromServer(menu.items));
    const [base, setBase] = useState(menu.items);
    const [editing, setEditing] = useState<EditItem | null>(null);
    const [problem, setProblem] = useState<string | null>(null);
    const mayPublish = can(user, 'content.publish');
    const dirty = JSON.stringify(toPayload(items)) !== JSON.stringify(toPayload(fromServer(menu.items)));
    const blocker = useBlocker(({ currentLocation, nextLocation }) => dirty && currentLocation.search + currentLocation.pathname !== nextLocation.search + nextLocation.pathname);

    if (base !== menu.items) {
        setBase(menu.items);
        setItems(fromServer(menu.items));
    }

    useEffect(() => {
        if (!dirty) return;
        const warn = (event: BeforeUnloadEvent) => event.preventDefault();
        window.addEventListener('beforeunload', warn);

        return () => window.removeEventListener('beforeunload', warn);
    }, [dirty]);

    const submit = () => {
        setProblem(null);
        save.mutate(toPayload(items), {
            onSuccess: () => toast.success('Menu saved', 'The website updates within seconds.'),
            onError: (error) => {
                if (!(error instanceof ApiError)) return;
                const first = Object.values(error.fieldErrors)[0]?.[0];
                setProblem(error.code === 'VALIDATION_FAILED' ? `Some items need fixing: ${first ?? 'check the labels and links.'}` : error.humanMessage);
            },
        });
    };

    const row = (item: EditItem, handle: ReactNode, child: boolean, topIndex: number) => (
        <div className={cn('flex items-center gap-3 py-3.5 pr-5', child ? 'pl-12' : 'pl-5', !item.is_visible && 'opacity-60')}>
            {handle ?? <span className="w-7" />}
            {child && <CornerDownRight className="size-4 shrink-0 text-ink-soft" aria-hidden="true" />}
            <div className="min-w-0 flex-1">
                <p className="truncate text-[15px] font-semibold text-ink">{item.label || 'Untitled'}</p>
                {pageMissing(item) ? (
                    <p className="truncate text-admin-small font-semibold text-danger">Page deleted · choose another page or remove this item</p>
                ) : documentMissing(item) ? (
                    <p className="truncate text-admin-small font-semibold text-danger">Document no longer in the library · choose another or remove this item</p>
                ) : (
                    <p className="truncate text-admin-small text-ink-soft">{hrefOf(item)}</p>
                )}
            </div>
            <span
                className={cn(
                    'hidden shrink-0 rounded-full px-2.5 py-[3px] text-admin-small font-semibold sm:inline-flex',
                    item.type === 'page' ? 'bg-sky-soft text-royal' : 'bg-stone text-ink-soft'
                )}
            >
                {TYPE_LABEL[item.type]}
            </span>
            {mayPublish && (
                <>
                    <button
                        type="button"
                        aria-label={item.is_visible ? `Hide ${item.label}` : `Show ${item.label}`}
                        aria-pressed={!item.is_visible}
                        onClick={() =>
                            setItems(
                                updateItem(items, item.uid, {
                                    is_visible: !item.is_visible,
                                })
                            )
                        }
                        className="rounded-full p-1.5 text-ink-soft hover:bg-paper hover:text-ink"
                    >
                        {item.is_visible ? <Eye className="size-4" aria-hidden="true" /> : <EyeOff className="size-4" aria-hidden="true" />}
                    </button>
                    <button
                        type="button"
                        aria-label={`Edit ${item.label}`}
                        onClick={() => setEditing(item)}
                        className="rounded-full p-1.5 text-ink-soft hover:bg-paper hover:text-ink"
                    >
                        <Pencil className="size-4" aria-hidden="true" />
                    </button>
                    <Dropdown
                        trigger={(props) => (
                            <button {...props} type="button" aria-label={`More for ${item.label}`} className="rounded-full p-1.5 text-ink-soft hover:bg-paper hover:text-ink">
                                <Ellipsis className="size-4" aria-hidden="true" />
                            </button>
                        )}
                        items={[
                            child
                                ? {
                                      label: 'Move out of the group',
                                      icon: <ArrowLeftToLine className="size-4" />,
                                      onSelect: () => setItems(outdent(items, item.uid)),
                                  }
                                : {
                                      label: 'Put under the item above',
                                      icon: <ArrowRightToLine className="size-4" />,
                                      onSelect: () => setItems(indent(items, item.uid)),
                                      disabled: topIndex === 0,
                                  },
                            {
                                label: 'Remove from menu',
                                icon: <Trash2 className="size-4" />,
                                tone: 'danger',
                                onSelect: () => setItems(removeItem(items, item.uid)),
                            },
                        ]}
                    />
                </>
            )}
        </div>
    );

    const visibleTop = items.filter((item) => item.is_visible);

    return (
        <>
            <Header
                actions={
                    <>
                        {dirty && (
                            <span className="inline-flex items-center gap-1.5 text-admin-small text-ink-soft">
                                <span aria-hidden="true" className="size-2 rounded-full bg-warning" /> Unsaved changes
                            </span>
                        )}
                        {mayPublish && (
                            <Button icon={<Check className="size-4" />} disabled={!dirty} loading={save.isPending} onClick={submit}>
                                Save menu
                            </Button>
                        )}
                    </>
                }
            />
            {!mayPublish && <FormAlert>Menus change the live website, so saving them needs the content.publish permission. You can look but not change.</FormAlert>}
            {problem && <FormAlert>{problem}</FormAlert>}

            <div className="grid items-start gap-6 lg:max-[1399px]:grid-cols-[300px_minmax(0,1fr)] min-[1400px]:grid-cols-[240px_minmax(0,1fr)_320px]">
                <div className="lg:max-[1399px]:col-start-1 lg:max-[1399px]:row-start-1">{menuList}</div>

                <div className="flex min-w-0 flex-col gap-5 lg:max-[1399px]:col-start-2 lg:max-[1399px]:row-span-2 lg:max-[1399px]:row-start-1">
                    <section aria-label="Live preview" className="flex flex-col gap-3.5 rounded-sheet border border-line bg-white p-5">
                        <p className="font-display text-admin-overline font-bold tracking-[1.1px] text-ink-soft uppercase">Live preview</p>
                        {visibleTop.length === 0 ? (
                            <p className="text-admin-body text-ink-soft">Nothing visible in this menu yet.</p>
                        ) : (
                            <div className="flex max-w-full flex-wrap gap-1 self-start rounded-[28px] border border-line p-1.5">
                                {visibleTop.map((item, index) => (
                                    <span key={item.uid} className={cn('rounded-full px-5 py-2 text-[15px] font-semibold', index === 0 ? 'bg-navy text-white' : 'text-ink')}>
                                        {item.label}
                                    </span>
                                ))}
                            </div>
                        )}
                    </section>

                    {items.length === 0 ? (
                        <EmptyState icon={<MenuIcon className="size-5" />} title="This menu is empty" description="Add pages or links from the panel on the right." />
                    ) : (
                        <div className="overflow-hidden rounded-sheet border border-line bg-white">
                            <SortableList
                                items={items}
                                disabled={!mayPublish}
                                getKey={(item) => item.uid}
                                getLabel={(item) => item.label}
                                onMove={(from, to) => setItems(moveItem(items, from, to))}
                                className="divide-y divide-line"
                                renderItem={(item, index, handle) => (
                                    <div>
                                        {row(item, handle, false, index)}
                                        {item.children.length > 0 && (
                                            <SortableList
                                                items={item.children}
                                                disabled={!mayPublish}
                                                getKey={(childItem) => childItem.uid}
                                                getLabel={(childItem) => childItem.label}
                                                onMove={(from, to) => setItems(moveChild(items, item.uid, from, to))}
                                                className="divide-y divide-line border-t border-line bg-paper/40"
                                                renderItem={(childItem, _childIndex, childHandle) => row(childItem, childHandle, true, index)}
                                            />
                                        )}
                                    </div>
                                )}
                            />
                        </div>
                    )}
                    <p className="text-admin-small text-ink-soft">
                        {countItems(items)} {countItems(items) === 1 ? 'item' : 'items'}. Hidden items stay in the menu but don’t show on the website; links to pages that aren’t
                        published are left out automatically.
                    </p>
                </div>

                <div className="lg:max-[1399px]:col-start-1 lg:max-[1399px]:row-start-2">
                    <AddItemsPanel pages={pages} loading={pagesLoading} disabled={!mayPublish} onAdd={(added) => setItems([...items, ...added])} />
                </div>
            </div>

            {editing && (
                <ItemDialog
                    item={editing}
                    pages={pages}
                    onClose={() => setEditing(null)}
                    onSave={(next) => {
                        setItems(updateItem(items, next.uid, next));
                        setEditing(null);
                    }}
                />
            )}

            <ConfirmDialog
                open={blocker.state === 'blocked'}
                tone="primary"
                title="Leave without saving?"
                description={`Your changes to ${menu.name} haven’t been saved and will be lost.`}
                confirmLabel="Discard changes"
                onConfirm={() => blocker.proceed?.()}
                onClose={() => blocker.reset?.()}
            />
        </>
    );
}
