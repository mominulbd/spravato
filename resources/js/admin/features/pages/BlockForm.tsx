import type { ReactNode } from 'react';
import { useState } from 'react';
import { ArrowDown, ArrowUp, ChevronDown, FileText, Info, Plus, Trash2, X } from 'lucide-react';
import { Button, Select, TagInput, TextInput, Textarea, Toggle, cn } from '@shared/ui';
import { CONTENT_TOKENS_HELP, type ContentLink } from '@admin/lib/content';
import { ImageField } from '@admin/features/media/ImageField';
import { MediaPicker } from '@admin/features/media/MediaPicker';
import { useMediaItem } from '@admin/features/media/api';
import { emptyRow, getPath, setPath } from './editorState';
import type { BlockData, BlockField } from './types';

/**
 * Renders a block's form from its field schema (GET admin/pages/block-types),
 * the same schema the API validates against. Errors are keyed by field path
 * (`primary.href`, `facts.1.value`), exactly as the API reports them.
 */
interface BlockFormProps {
    fields: BlockField[];
    data: BlockData;
    onChange: (data: BlockData) => void;
    errors?: Record<string, string>;
    /** Path prefix for nested repeater rows, e.g. `facts.0.` */
    prefix?: string;
    /** Hide fields that don't apply to the current choices (fieldRules.ts). */
    isVisible?: (field: BlockField, data: BlockData) => boolean;
}

export function BlockForm({ fields, data, onChange, errors = {}, prefix = '', isVisible }: BlockFormProps) {
    const set = (name: string, value: unknown) => onChange(setPath(data, name, value));
    const sections: { group: string | null; fields: BlockField[] }[] = [];

    for (const field of fields.filter((item) => !isVisible || isVisible(item, data))) {
        const last = sections[sections.length - 1];

        if (last && field.group && last.group === field.group) last.fields.push(field);
        else sections.push({ group: field.group ?? null, fields: [field] });
    }

    const hasLinks = prefix === '' && fields.some((field) => field.type === 'link');

    return (
        <div className="flex flex-col gap-5">
            {sections.map((section, index) =>
                section.group === null ? (
                    section.fields.map((field) => <FieldControl key={field.name} field={field} value={data[field.name]} onChange={(value) => set(field.name, value)} errors={errors} path={prefix + field.name} />)
                ) : (
                    <GroupBox key={`${section.group}-${index}`} title={section.group} fields={section.fields}>
                        {section.fields.map((field) => (
                            <FieldControl key={field.name} field={field} value={data[field.name]} onChange={(value) => set(field.name, value)} errors={errors} path={prefix + field.name} inGroup />
                        ))}
                    </GroupBox>
                ),
            )}
            {hasLinks && (
                <p className="flex items-start gap-2 text-admin-small text-ink-soft">
                    <Info className="mt-0.5 size-3.5 shrink-0 text-royal" aria-hidden="true" />
                    {CONTENT_TOKENS_HELP}
                </p>
            )}
        </div>
    );
}

/** Figma 07 "PRIMARY BUTTON / SECONDARY BUTTON": soft boxes; links sit side by side. */
function GroupBox({ title, fields, children }: { title: string; fields: BlockField[]; children: ReactNode }) {
    const allLinks = fields.every((field) => field.type === 'link') && fields.length > 1;

    return (
        <fieldset className="@container flex flex-col gap-3 rounded-[18px] bg-paper p-4">
            <legend className="sr-only">{title}</legend>
            {!allLinks && <p aria-hidden="true" className="font-display text-[10px] leading-[1.3] font-bold tracking-[1.1px] text-ink-soft uppercase">{title}</p>}
            <div className={cn('flex flex-col gap-4', allLinks && 'grid gap-3 @lg:grid-cols-2')}>{children}</div>
        </fieldset>
    );
}

function label(field: BlockField): ReactNode {
    return (
        <>
            {field.label}
            {field.required && (
                <span className="text-danger" aria-hidden="true">
                    {' '}
                    *
                </span>
            )}
        </>
    );
}

/** Headlines always show "58 / 80"; plain text only once it nears the limit. */
function counter(value: string, max: number | undefined, always: boolean): ReactNode {
    return max && (always || value.length >= max * 0.8) ? <span className="shrink-0 whitespace-nowrap">{`${value.length} / ${max}`}</span> : undefined;
}

interface FieldControlProps {
    field: BlockField;
    value: unknown;
    onChange: (value: unknown) => void;
    errors: Record<string, string>;
    path: string;
    inGroup?: boolean;
}

export function FieldControl({ field, value, onChange, errors, path, inGroup = false }: FieldControlProps) {
    const error = errors[path];
    const text = typeof value === 'string' ? value : '';

    switch (field.type) {
        case 'text':
        case 'headline':
            return (
                <TextInput
                    label={label(field)}
                    value={text}
                    maxLength={field.max}
                    required={field.required}
                    help={field.help}
                    error={error}
                    aside={counter(text, field.max, field.type === 'headline')}
                    onChange={(event) => onChange(event.target.value)}
                />
            );
        case 'textarea':
        case 'markdown':
            return (
                <Textarea
                    label={label(field)}
                    value={text}
                    maxLength={field.max}
                    required={field.required}
                    help={field.help}
                    error={error}
                    rows={field.type === 'markdown' ? 6 : 3}
                    onChange={(event) => onChange(event.target.value)}
                />
            );
        case 'link':
            return <LinkControl field={field} value={value} onChange={onChange} errors={errors} path={path} inGroup={inGroup} />;
        case 'image':
            return <ImageField label={field.label} value={typeof value === 'number' ? value : null} onChange={onChange} help={field.help} error={error} />;
        case 'document':
            return <DocumentControl label={field.label} value={typeof value === 'number' ? value : null} onChange={onChange} help={field.help} error={error} />;
        case 'list':
            return <ListControl field={field} value={Array.isArray(value) ? (value as string[]) : []} onChange={onChange} errors={errors} path={path} />;
        case 'repeater':
            return <RepeaterControl field={field} value={Array.isArray(value) ? (value as BlockData[]) : []} onChange={onChange} errors={errors} path={path} />;
        case 'select':
        case 'source':
            return (
                <Select label={label(field)} value={text} help={field.help} error={error} onChange={(event) => onChange(event.target.value)}>
                    {(field.options ?? []).length === 0 && <option value={text}>{text || '—'}</option>}
                    {(field.options ?? []).map((option) => (
                        <option key={option.value} value={option.value}>
                            {option.label}
                        </option>
                    ))}
                </Select>
            );
        case 'toggle':
            return (
                <div className="flex flex-col gap-1">
                    <Toggle label={field.label} description={field.help} checked={Boolean(value)} onChange={(event) => onChange(event.target.checked)} />
                    {error && <p className="text-admin-small text-danger">{error}</p>}
                </div>
            );
        default:
            return null;
    }
}

/**
 * A PDF from the library (official documents). Stored as the media id, so
 * the website link follows the file when it is replaced.
 */
function DocumentControl({ label, value, onChange, help, error, compact = false }: { label: string; value: number | null; onChange: (id: number | null) => void; help?: string; error?: string; compact?: boolean }) {
    const [picking, setPicking] = useState(false);
    const media = useMediaItem(value);
    const name = value === null ? 'No document chosen.' : media.data ? media.data.filename : media.isError ? 'This file is no longer in the library.' : 'Loading…';

    return (
        <div className="flex flex-col gap-1.5">
            {!compact && <span className="text-admin-label font-semibold text-ink">{label}</span>}
            <div className={cn('flex items-center gap-3 rounded-card border bg-white p-3', error ? 'border-danger' : 'border-line')}>
                <FileText className="size-5 shrink-0 text-royal" aria-hidden="true" />
                <p className={cn('min-w-0 flex-1 truncate text-admin-body', value !== null && media.isError ? 'font-semibold text-danger' : 'text-ink')}>{name}</p>
                <Button size="sm" variant="secondary" onClick={() => setPicking(true)}>
                    {value === null ? 'Choose document' : 'Replace'}
                </Button>
                {value !== null && (
                    <Button size="sm" variant="ghost" icon={<X className="size-3.5" />} aria-label={`Remove ${label.toLowerCase()}`} onClick={() => onChange(null)}>
                        Remove
                    </Button>
                )}
            </div>
            {error && <p className="text-admin-small text-danger">{error}</p>}
            {help && !error && <p className="text-admin-small text-ink-soft">{help}</p>}
            {picking && <MediaPicker open kind="document" title="Choose a document" onClose={() => setPicking(false)} onSelect={(picked) => onChange(picked.id)} />}
        </div>
    );
}

type StoredLink = ContentLink & { media_id?: number };

function LinkControl({ field, value, onChange, errors, path, inGroup }: Omit<FieldControlProps, 'inGroup'> & { inGroup: boolean }) {
    const link: StoredLink = value !== null && typeof value === 'object' ? { label: '', href: '', ...(value as Partial<StoredLink>) } : { label: '', href: '' };
    const document = typeof link.media_id === 'number' ? link.media_id : null;
    const body = (
        <>
            <TextInput label="Label" value={link.label} maxLength={80} error={errors[`${path}.label`]} onChange={(event) => onChange({ ...link, label: event.target.value })} />
            {document === null ? (
                <div className="flex flex-col gap-1">
                    <TextInput label="Link" value={link.href} maxLength={190} placeholder="/contact, #faq, https://…, tel:clinic" error={errors[`${path}.href`] ?? errors[path]} onChange={(event) => onChange({ ...link, href: event.target.value })} />
                    <DocumentPick onPick={(id) => onChange({ label: link.label, href: '', media_id: id })} />
                </div>
            ) : (
                <DocumentControl
                    label="Document"
                    value={document}
                    error={errors[`${path}.media_id`] ?? errors[path]}
                    onChange={(id) => onChange(id === null ? { label: link.label, href: '' } : { ...link, media_id: id })}
                />
            )}
        </>
    );

    if (inGroup) {
        return (
            <fieldset className="flex min-w-0 flex-col gap-3 rounded-[16px] bg-white p-4">
                <legend className="float-left w-full font-display text-[10px] leading-[1.3] font-bold tracking-[1.1px] text-ink-soft uppercase">{field.label}</legend>
                {body}
            </fieldset>
        );
    }

    return (
        <fieldset className="@container flex flex-col gap-3 rounded-[18px] bg-paper p-4">
            <legend className="float-left mb-1 w-full font-display text-[10px] leading-[1.3] font-bold tracking-[1.1px] text-ink-soft uppercase">{field.label}</legend>
            <div className="grid gap-3 @md:grid-cols-2">{body}</div>
        </fieldset>
    );
}

/** "Link to a document instead" under a link's address. */
function DocumentPick({ onPick }: { onPick: (id: number) => void }) {
    const [picking, setPicking] = useState(false);

    return (
        <>
            <button type="button" className="self-start text-admin-small font-semibold text-royal hover:underline" onClick={() => setPicking(true)}>
                Link to a document instead
            </button>
            {picking && <MediaPicker open kind="document" title="Choose a document" onClose={() => setPicking(false)} onSelect={(picked) => onPick(picked.id)} />}
        </>
    );
}

function ListControl({ field, value, onChange, errors, path }: { field: BlockField; value: string[]; onChange: (value: unknown) => void; errors: Record<string, string>; path: string }) {
    const max = field.max ?? 200;
    const maxItems = field.max_items ?? 10;
    const itemError = value.map((_, index) => errors[`${path}.${index}`]).find(Boolean);

    if (max <= 80) {
        return <TagInput label={field.label} value={value} onChange={onChange} help={field.help} error={errors[path] ?? itemError} max={maxItems} maxLength={max} />;
    }

    const move = (from: number, to: number) => {
        const next = [...value];
        const [item] = next.splice(from, 1);
        next.splice(to, 0, item ?? '');
        onChange(next);
    };

    return (
        <fieldset className="flex flex-col gap-2">
            <legend className="mb-1.5 flex w-full items-center justify-between text-admin-label font-semibold text-ink">
                {field.label}
                <span className="text-admin-small font-normal text-ink-soft">
                    {value.length} / {maxItems}
                </span>
            </legend>
            {value.map((item, index) => (
                <div key={index} className="flex items-start gap-2">
                    <span aria-hidden="true" className="mt-[18px] size-2 shrink-0 rounded-full bg-royal" />
                    <Textarea
                        aria-label={`${field.label} item ${index + 1}`}
                        value={item}
                        maxLength={max}
                        rows={1}
                        className="flex-1"
                        error={errors[`${path}.${index}`]}
                        onChange={(event) => onChange(value.map((current, i) => (i === index ? event.target.value : current)))}
                    />
                    <RowTools index={index} count={value.length} name={`item ${index + 1}`} onMove={move} onRemove={() => onChange(value.filter((_, i) => i !== index))} />
                </div>
            ))}
            {errors[path] && <p className="text-admin-small text-danger">{errors[path]}</p>}
            <Button variant="soft" size="sm" className="self-start" icon={<Plus className="size-3.5" aria-hidden="true" />} disabled={value.length >= maxItems} onClick={() => onChange([...value, ''])}>
                Add item
            </Button>
        </fieldset>
    );
}

function RowTools({ index, count, name, onMove, onRemove }: { index: number; count: number; name: string; onMove: (from: number, to: number) => void; onRemove: () => void }) {
    const tool = 'rounded-full p-2 text-ink-soft hover:bg-stone/60 hover:text-ink disabled:opacity-30 disabled:hover:bg-transparent';

    return (
        <div className="flex shrink-0 items-center pt-1">
            <button type="button" className={tool} aria-label={`Move ${name} up`} disabled={index === 0} onClick={() => onMove(index, index - 1)}>
                <ArrowUp className="size-3.5" aria-hidden="true" />
            </button>
            <button type="button" className={tool} aria-label={`Move ${name} down`} disabled={index === count - 1} onClick={() => onMove(index, index + 1)}>
                <ArrowDown className="size-3.5" aria-hidden="true" />
            </button>
            <button type="button" className={cn(tool, 'hover:text-danger')} aria-label={`Remove ${name}`} onClick={onRemove}>
                <Trash2 className="size-3.5" aria-hidden="true" />
            </button>
        </div>
    );
}

function RepeaterControl({ field, value, onChange, errors, path }: { field: BlockField; value: BlockData[]; onChange: (value: unknown) => void; errors: Record<string, string>; path: string }) {
    const subFields = field.fields ?? [];
    const maxItems = field.max_items ?? 10;
    const [open, setOpen] = useState<number | null>(value.length > 0 ? 0 : null);

    const move = (from: number, to: number) => {
        const next = [...value];
        const [row] = next.splice(from, 1);
        next.splice(to, 0, row ?? {});
        onChange(next);
        setOpen(to);
    };

    const titleOf = (row: BlockData, index: number) => {
        const first = subFields.map((sub) => getPath(row, sub.type === 'link' ? `${sub.name}.label` : sub.name)).find((v): v is string => typeof v === 'string' && v.trim() !== '');

        return first ? first.replace(/\*/g, '') : `${field.label} ${index + 1}`;
    };

    return (
        <fieldset className="flex flex-col gap-2">
            <legend className="mb-1.5 flex w-full items-center justify-between text-admin-label font-semibold text-ink">
                {field.label}
                <span className="text-admin-small font-normal text-ink-soft">
                    {value.length} / {maxItems}
                </span>
            </legend>
            {value.map((row, index) => {
                const rowHasError = Object.keys(errors).some((key) => key.startsWith(`${path}.${index}.`));
                const expanded = open === index || rowHasError;

                return (
                    <div key={index} className={cn('rounded-[16px] border bg-white', rowHasError ? 'border-danger' : 'border-line')}>
                        <div className="flex items-center gap-2 pr-2 pl-4">
                            <button type="button" aria-expanded={expanded} onClick={() => setOpen(expanded ? null : index)} className="flex min-w-0 flex-1 items-center gap-2.5 py-3 text-left text-admin-body font-semibold text-ink">
                                <ChevronDown className={cn('size-4 shrink-0 text-ink-soft transition-transform', !expanded && '-rotate-90')} aria-hidden="true" />
                                <span className="font-display text-[11px] font-bold text-royal">{String(index + 1).padStart(2, '0')}</span>
                                <span className="min-w-0 truncate">{titleOf(row, index)}</span>
                            </button>
                            <RowTools index={index} count={value.length} name={titleOf(row, index)} onMove={move} onRemove={() => onChange(value.filter((_, i) => i !== index))} />
                        </div>
                        {expanded && (
                            <div className="border-t border-line p-4">
                                <BlockForm fields={subFields} data={row} prefix={`${path}.${index}.`} errors={errors} onChange={(next) => onChange(value.map((current, i) => (i === index ? next : current)))} />
                            </div>
                        )}
                    </div>
                );
            })}
            {errors[path] && <p className="text-admin-small text-danger">{errors[path]}</p>}
            <Button
                variant="soft"
                size="sm"
                className="self-start"
                icon={<Plus className="size-3.5" aria-hidden="true" />}
                disabled={value.length >= maxItems}
                onClick={() => {
                    onChange([...value, emptyRow(subFields)]);
                    setOpen(value.length);
                }}
            >
                Add {field.label.toLowerCase().replace(/s$/, '').replace(/ \(.*$/, '')}
            </Button>
        </fieldset>
    );
}
