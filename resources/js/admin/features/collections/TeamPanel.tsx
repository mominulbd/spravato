import type { FormEvent } from 'react';
import { useState } from 'react';
import { Check, Crown, Pencil, Trash2, Users } from 'lucide-react';
import { ApiError } from '@shared/api/client';
import { firstErrors, type Errors } from '@shared/forms/validate';
import {
    Badge,
    Button,
    Checkbox,
    ConfirmDialog,
    Drawer,
    EmptyState,
    ErrorState,
    Select,
    Skeleton,
    SortableList,
    TagInput,
    TextInput,
    Textarea,
    Toggle,
    cn,
    useToast,
} from '@shared/ui';
import { FormAlert } from '@admin/features/auth/FormAlert';
import { ImageField } from '@admin/features/media/ImageField';
import { useCollectionItems, useDeleteItem, useSaveItem } from './api';
import { PublishedToggle, ReadOnlyNote, VisibilityBadge } from './ItemShell';
import { useCollectionAccess } from './permissions';
import { TEAM_PAGES, type TeamMember } from './types';
import { useLocalOrder } from './useLocalOrder';

/** Figma 14: the care team, drag to reorder, edit in a drawer. */
export function TeamPanel({ creating, onCreated }: { creating: boolean; onCreated: () => void }) {
    const list = useCollectionItems<TeamMember>('team');
    const access = useCollectionAccess();
    const items = list.data?.items ?? [];
    const order = useLocalOrder('team', items);
    const [editing, setEditing] = useState<TeamMember | null>(null);

    if (list.isPending) return <ListSkeleton />;
    if (list.isError) return <ErrorState title="The team didn’t load" description="Check your connection and try again." onRetry={() => void list.refetch()} />;

    return (
        <>
            {items.length === 0 ? (
                <EmptyState icon={<Users className="size-5" />} title="No team members yet" description="Add the people patients will meet." />
            ) : (
                <div className="overflow-hidden rounded-sheet border border-line bg-white">
                    <SortableList
                        items={order.order}
                        disabled={!access.mayPublish}
                        getKey={(member) => member.id}
                        getLabel={(member) => member.name}
                        onMove={order.move}
                        className="divide-y divide-line"
                        renderItem={(member, _index, handle) => (
                            <div className={cn('flex items-center gap-4 px-6 py-5 transition-colors hover:bg-paper/60', editing?.id === member.id && 'bg-sky-soft')}>
                                {handle ?? <span className="w-7" />}
                                <Avatar member={member} />
                                <div className="min-w-0 flex-1">
                                    <p className="flex flex-wrap items-baseline gap-x-2.5 gap-y-0.5">
                                        <span className="text-[17px] font-semibold text-ink">{member.name}</span>
                                        {member.credentials && <span className="text-admin-small font-semibold text-ink-soft">{member.credentials}</span>}
                                    </p>
                                    <p className="truncate text-admin-body text-ink-soft">{member.role_title}</p>
                                </div>
                                <div className="hidden items-center gap-2 md:flex">
                                    {member.is_founder && (
                                        <Badge tone="navy">
                                            <Crown className="size-3" aria-hidden="true" /> Founder
                                        </Badge>
                                    )}
                                    <VisibilityBadge published={member.is_published} />
                                </div>
                                <Button size="sm" variant="secondary" icon={<Pencil className="size-3.5" />} onClick={() => setEditing(member)} aria-label={`Edit ${member.name}`}>
                                    Edit
                                </Button>
                            </div>
                        )}
                    />
                </div>
            )}
            {(editing !== null || creating) && (
                <TeamDrawer
                    member={editing}
                    onClose={() => {
                        setEditing(null);
                        if (creating) onCreated();
                    }}
                />
            )}
        </>
    );
}

export function Avatar({ member }: { member: Pick<TeamMember, 'photo' | 'initials'> }) {
    const classes = 'size-14 text-[19px]';

    return member.photo ? (
        <img
            src={member.photo.sources.webp?.[0]?.url ?? member.photo.url}
            alt=""
            className={cn('shrink-0 rounded-full object-cover', classes)}
            style={{
                objectPosition: `${member.photo.focal.x}% ${member.photo.focal.y}%`,
            }}
        />
    ) : (
        <span aria-hidden="true" className={cn('inline-flex shrink-0 items-center justify-center rounded-full bg-sky font-semibold text-navy', classes)}>
            {member.initials}
        </span>
    );
}

export function ListSkeleton() {
    return (
        <div className="flex flex-col gap-3" aria-busy="true" aria-label="Loading">
            {Array.from({ length: 4 }, (_, index) => (
                <Skeleton key={index} className="h-20 w-full rounded-card" />
            ))}
        </div>
    );
}

interface TeamForm {
    name: string;
    credentials: string;
    role_title: string;
    bio: string;
    highlights: string[];
    photo_id: number | null;
    show_on: string[];
    is_founder: boolean;
    schema_type: 'Physician' | 'Person';
    licence_number: string;
    is_published: boolean;
}

function toForm(member: TeamMember | null): TeamForm {
    return {
        name: member?.name ?? '',
        credentials: member?.credentials ?? '',
        role_title: member?.role_title ?? '',
        bio: member?.bio ?? '',
        highlights: member?.highlights ?? [],
        photo_id: member?.photo?.id ?? null,
        show_on: member?.show_on ?? ['home'],
        is_founder: member?.is_founder ?? false,
        schema_type: member?.schema_type ?? 'Person',
        licence_number: member?.licence_number ?? '',
        is_published: member?.is_published ?? false,
    };
}

function TeamDrawer({ member, onClose }: { member: TeamMember | null; onClose: () => void }) {
    const toast = useToast();
    const access = useCollectionAccess();
    const save = useSaveItem<TeamMember>('team');
    const remove = useDeleteItem('team');
    const [form, setForm] = useState<TeamForm>(() => toForm(member));
    const [errors, setErrors] = useState<Errors>({});
    const [alert, setAlert] = useState<string | null>(null);
    const [confirming, setConfirming] = useState(false);
    const editable = access.mayChange(member);
    const set = <K extends keyof TeamForm>(key: K, value: TeamForm[K]) => setForm((current) => ({ ...current, [key]: value }));
    const pages = form.is_published ? form.show_on.length : 0;

    const submit = (event: FormEvent) => {
        event.preventDefault();
        setAlert(null);
        const text = (value: string) => (value.trim() === '' ? null : value.trim());
        const body: Record<string, unknown> = {
            name: form.name.trim(),
            credentials: text(form.credentials),
            role_title: form.role_title.trim(),
            bio: text(form.bio),
            highlights: form.highlights,
            photo_id: form.photo_id,
            show_on: form.show_on,
            is_founder: form.is_founder,
            schema_type: form.schema_type,
            licence_number: text(form.licence_number),
            ...(access.mayPublish ? { is_published: form.is_published } : {}),
        };

        save.mutate(
            { id: member?.id ?? null, body },
            {
                onSuccess: () => {
                    toast.success(member ? 'Changes saved' : 'Team member added', form.is_published ? 'The website updates within seconds.' : 'Hidden until published.');
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
                    <span className="font-display text-admin-overline font-bold tracking-[1.1px] text-ink-soft uppercase">{member ? 'Edit team member' : 'New team member'}</span>
                    <span>{form.name.trim() || 'Team member'}</span>
                </span>
            }
            footer={
                <>
                    <span className="mr-auto text-admin-small text-ink-soft">
                        {pages > 0 ? `Changes publish to ${pages} ${pages === 1 ? 'page' : 'pages'}` : 'Not shown on the website'}
                    </span>
                    <Button variant="ghost" onClick={onClose} disabled={save.isPending}>
                        Cancel
                    </Button>
                    {editable && (
                        <Button type="submit" form="team-member" icon={<Check className="size-4" />} loading={save.isPending}>
                            Save changes
                        </Button>
                    )}
                </>
            }
        >
            <form id="team-member" onSubmit={submit} noValidate className="flex flex-col gap-5">
                {alert && <FormAlert>{alert}</FormAlert>}
                {!editable && <ReadOnlyNote>This person is shown on the website. Changing them needs the content.publish permission.</ReadOnlyNote>}
                <fieldset disabled={!editable} className="flex min-w-0 flex-col gap-5">
                    <ImageField
                        label="Headshot"
                        value={form.photo_id}
                        onChange={(value) => set('photo_id', value)}
                        error={errors.photo_id}
                        help="Square photos work best (800 × 800)."
                    />
                    <div className="grid gap-4 sm:grid-cols-2">
                        <TextInput label="Full name" maxLength={120} value={form.name} onChange={(e) => set('name', e.target.value)} error={errors.name} />
                        <TextInput
                            label="Credentials"
                            maxLength={60}
                            placeholder="MSN, PMHNP-BC"
                            value={form.credentials}
                            onChange={(e) => set('credentials', e.target.value)}
                            error={errors.credentials}
                        />
                    </div>
                    <TextInput label="Role" maxLength={120} value={form.role_title} onChange={(e) => set('role_title', e.target.value)} error={errors.role_title} />
                    <Textarea
                        label="Bio"
                        rows={5}
                        maxLength={600}
                        value={form.bio}
                        onChange={(e) => set('bio', e.target.value)}
                        error={errors.bio}
                        help="Shown on the founder card and in Physician schema."
                    />
                    <TagInput label="Highlights" value={form.highlights} onChange={(value) => set('highlights', value)} max={8} maxLength={60} error={errors.highlights} />
                    <fieldset className="flex flex-col gap-2.5">
                        <legend className="mb-2.5 text-admin-label font-semibold text-ink">Show on</legend>
                        <div className="flex flex-wrap gap-x-6 gap-y-2">
                            {TEAM_PAGES.map((page) => (
                                <Checkbox
                                    key={page.value}
                                    label={page.label}
                                    checked={form.show_on.includes(page.value)}
                                    onChange={(event) =>
                                        set('show_on', event.target.checked ? [...form.show_on, page.value] : form.show_on.filter((value) => value !== page.value))
                                    }
                                />
                            ))}
                        </div>
                    </fieldset>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Select
                            label="Schema type"
                            value={form.schema_type}
                            onChange={(e) => set('schema_type', e.target.value as TeamForm['schema_type'])}
                            error={errors.schema_type}
                        >
                            <option value="Physician">Physician</option>
                            <option value="Person">Person</option>
                        </Select>
                        <TextInput
                            label="Licence number"
                            placeholder="Optional"
                            maxLength={40}
                            value={form.licence_number}
                            onChange={(e) => set('licence_number', e.target.value)}
                            error={errors.licence_number}
                        />
                    </div>
                    <Toggle
                        label="Founder"
                        description="Shown in the founder card and the chips that name the clinic’s provider."
                        checked={form.is_founder}
                        onChange={(e) => set('is_founder', e.target.checked)}
                    />
                    <PublishedToggle checked={form.is_published} onChange={(value) => set('is_published', value)} mayPublish={access.mayPublish} />
                </fieldset>
                {member && editable && (
                    <Button variant="ghost" className="self-start text-danger hover:bg-danger-soft" icon={<Trash2 className="size-4" />} onClick={() => setConfirming(true)}>
                        Remove from the team
                    </Button>
                )}
            </form>
            {member && (
                <ConfirmDialog
                    open={confirming}
                    title={`Remove ${member.name}?`}
                    description="They disappear from every page that shows the team. Their headshot stays in the media library."
                    confirmLabel="Remove"
                    busy={remove.isPending}
                    onClose={() => setConfirming(false)}
                    onConfirm={() =>
                        remove.mutate(member.id, {
                            onSuccess: () => {
                                toast.success('Removed', member.name);
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
