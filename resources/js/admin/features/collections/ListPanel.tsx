import type { FormEvent } from 'react';
import { useState } from 'react';
import { Check, ImageIcon, LayoutList, Pencil, Star, Trash2 } from 'lucide-react';
import { ApiError } from '@shared/api/client';
import { firstErrors, type Errors } from '@shared/forms/validate';
import { Button, ConfirmDialog, Drawer, EmptyState, ErrorState, SortableList, TextInput, Textarea, cn, useToast } from '@shared/ui';
import { FormAlert } from '@admin/features/auth/FormAlert';
import { ImageField } from '@admin/features/media/ImageField';
import { useCollectionItems, useDeleteItem, useSaveItem } from './api';
import { PublishedToggle, ReadOnlyNote, VisibilityBadge } from './ItemShell';
import { bodyOf, valuesOf, type ListSpec } from './listFields';
import { useCollectionAccess } from './permissions';
import { ListSkeleton } from './TeamPanel';
import type { CollectionItem, CollectionKey } from './types';
import { useLocalOrder } from './useLocalOrder';

/** Testimonials, insurance partners, trust points and journey steps. */
export function ListPanel({ type, spec, creating, onCreated }: { type: CollectionKey; spec: ListSpec; creating: boolean; onCreated: () => void }) {
    const list = useCollectionItems<CollectionItem>(type);
    const access = useCollectionAccess();
    const order = useLocalOrder(type, list.data?.items ?? []);
    const [editing, setEditing] = useState<CollectionItem | null>(null);

    if (list.isPending) return <ListSkeleton />;
    if (list.isError) return <ErrorState title="This collection didn’t load" description="Check your connection and try again." onRetry={() => void list.refetch()} />;

    return (
        <>
            {order.order.length === 0 ? (
                <EmptyState icon={<LayoutList className="size-5" />} title={`No ${spec.noun}s yet`} description={`Add the first ${spec.noun}. It stays hidden until published.`} />
            ) : (
                <div className="overflow-hidden rounded-sheet border border-line bg-white">
                    <SortableList
                        items={order.order}
                        disabled={!access.mayPublish}
                        getKey={(item) => item.id}
                        getLabel={(item) => spec.title(item)}
                        onMove={order.move}
                        className="divide-y divide-line"
                        renderItem={(item, index, handle) => {
                            const image = spec.image?.(item);
                            const subtitle = spec.subtitle(item);

                            return (
                                <div className={cn('flex items-center gap-4 px-6 py-4 transition-colors hover:bg-paper/60', editing?.id === item.id && 'bg-sky-soft')}>
                                    {handle ?? <span className="w-7" />}
                                    {image ? (
                                        <span className="flex h-12 w-24 shrink-0 items-center justify-center overflow-hidden rounded-[10px] border border-line bg-white p-1.5">
                                            {image.url ? (
                                                <img src={image.url} alt="" className="max-h-full max-w-full object-contain" />
                                            ) : (
                                                <ImageIcon className="size-4 text-ink-soft" aria-hidden="true" />
                                            )}
                                        </span>
                                    ) : (
                                        <span className="w-6 shrink-0 font-display text-admin-overline font-bold tracking-[1.1px] text-royal">
                                            {String(index + 1).padStart(2, '0')}
                                        </span>
                                    )}
                                    <div className="min-w-0 flex-1">
                                        <p className="line-clamp-2 text-[15px] font-semibold text-ink">{spec.title(item)}</p>
                                        {subtitle && <p className="truncate text-admin-body text-ink-soft">{subtitle}</p>}
                                    </div>
                                    <span className="hidden md:inline-flex">
                                        <VisibilityBadge published={item.is_published} />
                                    </span>
                                    <Button
                                        size="sm"
                                        variant="secondary"
                                        icon={<Pencil className="size-3.5" />}
                                        onClick={() => setEditing(item)}
                                        aria-label={`Edit ${spec.title(item)}`}
                                    >
                                        Edit
                                    </Button>
                                </div>
                            );
                        }}
                    />
                </div>
            )}
            {(editing !== null || creating) && (
                <ItemDrawer
                    type={type}
                    spec={spec}
                    item={editing}
                    onClose={() => {
                        setEditing(null);
                        if (creating) onCreated();
                    }}
                />
            )}
        </>
    );
}

function ItemDrawer({ type, spec, item, onClose }: { type: CollectionKey; spec: ListSpec; item: CollectionItem | null; onClose: () => void }) {
    const toast = useToast();
    const access = useCollectionAccess();
    const save = useSaveItem(type);
    const remove = useDeleteItem(type);
    const [values, setValues] = useState(() => valuesOf(spec, item));
    const [published, setPublished] = useState(item?.is_published ?? false);
    const [errors, setErrors] = useState<Errors>({});
    const [alert, setAlert] = useState<string | null>(null);
    const [confirming, setConfirming] = useState(false);
    const editable = access.mayChange(item);
    const set = (name: string, value: string | number | null) => setValues((current) => ({ ...current, [name]: value }));
    const text = (name: string) => String(values[name] ?? '');

    const submit = (event: FormEvent) => {
        event.preventDefault();
        setAlert(null);
        save.mutate(
            {
                id: item?.id ?? null,
                body: {
                    ...bodyOf(values),
                    ...(access.mayPublish ? { is_published: published } : {}),
                },
            },
            {
                onSuccess: () => {
                    toast.success(
                        item ? 'Changes saved' : `${spec.noun.charAt(0).toUpperCase()}${spec.noun.slice(1)} added`,
                        published ? 'The website updates within seconds.' : 'Hidden until published.'
                    );
                    onClose();
                },
                onError: (error) => {
                    if (!(error instanceof ApiError)) return;
                    setErrors(firstErrors(error.fieldErrors));
                    setAlert(error.code === 'VALIDATION_FAILED' ? 'Check the highlighted fields.' : error.humanMessage);
                },
            }
        );
    };

    return (
        <Drawer
            open
            onClose={onClose}
            title={
                <span className="flex flex-col gap-1">
                    <span className="font-display text-admin-overline font-bold tracking-[1.1px] text-ink-soft uppercase">{item ? `Edit ${spec.noun}` : `New ${spec.noun}`}</span>
                    <span className="line-clamp-2">{item ? spec.title(item) : `Add a ${spec.noun}`}</span>
                </span>
            }
            footer={
                <>
                    <Button variant="ghost" onClick={onClose} disabled={save.isPending}>
                        Cancel
                    </Button>
                    {editable && (
                        <Button type="submit" form="collection-item" icon={<Check className="size-4" />} loading={save.isPending}>
                            Save changes
                        </Button>
                    )}
                </>
            }
        >
            <form id="collection-item" onSubmit={submit} noValidate className="flex flex-col gap-5">
                {alert && <FormAlert>{alert}</FormAlert>}
                {!editable && <ReadOnlyNote>This is shown on the website. Changing it needs the content.publish permission.</ReadOnlyNote>}
                <fieldset disabled={!editable} className="flex min-w-0 flex-col gap-5">
                    {spec.fields.map((field) => {
                        switch (field.kind) {
                            case 'textarea':
                                return (
                                    <Textarea
                                        key={field.name}
                                        label={field.label}
                                        rows={4}
                                        maxLength={field.max}
                                        value={text(field.name)}
                                        onChange={(e) => set(field.name, e.target.value)}
                                        error={errors[field.name]}
                                        help={field.help}
                                    />
                                );
                            case 'image':
                                return (
                                    <ImageField
                                        key={field.name}
                                        label={field.label}
                                        value={typeof values[field.name] === 'number' ? (values[field.name] as number) : null}
                                        onChange={(id) => set(field.name, id)}
                                        error={errors[field.name]}
                                        help={field.help}
                                    />
                                );
                            case 'rating':
                                return (
                                    <Rating
                                        key={field.name}
                                        value={typeof values[field.name] === 'number' ? (values[field.name] as number) : null}
                                        onChange={(value) => set(field.name, value)}
                                    />
                                );
                            default:
                                return (
                                    <TextInput
                                        key={field.name}
                                        label={field.label}
                                        type={field.kind === 'url' ? 'url' : 'text'}
                                        maxLength={field.max}
                                        placeholder={field.placeholder}
                                        value={text(field.name)}
                                        onChange={(e) => set(field.name, e.target.value)}
                                        error={errors[field.name]}
                                        help={field.help}
                                    />
                                );
                        }
                    })}
                    <PublishedToggle checked={published} onChange={setPublished} mayPublish={access.mayPublish} />
                </fieldset>
                {item && editable && (
                    <Button variant="ghost" className="self-start text-danger hover:bg-danger-soft" icon={<Trash2 className="size-4" />} onClick={() => setConfirming(true)}>
                        Delete {spec.noun}
                    </Button>
                )}
            </form>
            {item && (
                <ConfirmDialog
                    open={confirming}
                    title={`Delete this ${spec.noun}?`}
                    description={item.is_published ? 'It disappears from the website at once.' : 'The hidden draft is removed.'}
                    confirmLabel="Delete"
                    busy={remove.isPending}
                    onClose={() => setConfirming(false)}
                    onConfirm={() =>
                        remove.mutate(item.id, {
                            onSuccess: () => {
                                toast.success('Deleted');
                                onClose();
                            },
                            onError: (error) => {
                                setConfirming(false);
                                setAlert(error instanceof ApiError ? error.humanMessage : 'That didn’t work.');
                            },
                        })
                    }
                />
            )}
        </Drawer>
    );
}

function Rating({ value, onChange }: { value: number | null; onChange: (value: number | null) => void }) {
    return (
        <fieldset className="flex flex-col gap-2">
            <legend className="mb-2 text-admin-label font-semibold text-ink">Rating (optional)</legend>
            <div className="flex items-center gap-1">
                {[1, 2, 3, 4, 5].map((star) => (
                    <button
                        key={star}
                        type="button"
                        aria-label={`${star} star${star > 1 ? 's' : ''}`}
                        aria-pressed={value === star}
                        onClick={() => onChange(value === star ? null : star)}
                        className="rounded-md p-1 text-royal hover:bg-sky-soft"
                    >
                        <Star className={cn('size-5', value !== null && star <= value ? 'fill-royal' : 'fill-none')} aria-hidden="true" />
                    </button>
                ))}
                {value !== null && (
                    <button type="button" onClick={() => onChange(null)} className="ml-2 text-admin-small font-semibold text-ink-soft hover:text-ink">
                        Clear
                    </button>
                )}
            </div>
        </fieldset>
    );
}
