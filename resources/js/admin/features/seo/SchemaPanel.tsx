import { useState } from 'react';
import { Building2, Code2, ExternalLink, FileText, Globe, HeartPulse, CircleHelp, CornerUpRight, Newspaper, Stethoscope, Users } from 'lucide-react';
import { Badge, Button, Dialog, Select, TextInput, Textarea, Toggle, cn, useToast } from '@shared/ui';
import { STATUS_DOT } from './format';
import { SeoCard } from './parts';
import type { SchemaPiece } from './types';
import type { SeoDraft } from './useSeoDraft';

const ICONS: Record<string, typeof Globe> = {
    clinic: Building2,
    website: Globe,
    webpage: FileText,
    breadcrumbs: CornerUpRight,
    faq: CircleHelp,
    article: Newspaper,
    therapy: Stethoscope,
};

const SOURCE_TONE: Record<string, string> = {
    'From clinic settings': 'bg-navy text-white',
    'Site-wide': 'bg-stone text-ink-soft',
    Automatic: 'bg-stone text-ink-soft',
};

/** Figma 10: the JSON-LD graph for this page, piece by piece, with the output and its checks. */
export function SchemaPanel({ draft, canEdit }: { draft: SeoDraft; canEdit: boolean }) {
    const { values, live, errors, set } = draft;
    const { schema } = live;
    const toast = useToast();
    const [custom, setCustom] = useState<string | null>(null);
    const [customError, setCustomError] = useState<string | null>(null);
    const disabled = values.schema_disabled ?? [];
    const problems = schema.validation.filter((item) => item.status !== 'good').length;
    const json = `<script type="application/ld+json">\n${JSON.stringify({ '@context': 'https://schema.org', '@graph': schema.graph }, null, 2)}\n</script>`;

    const toggle = (piece: SchemaPiece, on: boolean) => set('schema_disabled', on ? disabled.filter((key) => key !== piece.key) : [...disabled, piece.key]);

    const saveCustom = () => {
        try {
            const parsed: unknown = JSON.parse(custom ?? '');
            const nodes = Array.isArray(parsed) ? parsed : [parsed];

            if (nodes.length === 0 || !nodes.every((node) => typeof node === 'object' && node !== null && typeof (node as Record<string, unknown>)['@type'] === 'string')) {
                setCustomError('Each node needs an "@type", e.g. {"@type": "MedicalOrganization", "name": "…"}.');

                return;
            }

            set('schema_custom', parsed as Record<string, unknown> | Record<string, unknown>[]);
            setCustom(null);
        } catch {
            setCustomError('That isn’t valid JSON. Check quotes and commas.');
        }
    };

    return (
        <div className="grid items-start gap-5 xl:grid-cols-[minmax(0,1fr)_minmax(0,460px)]">
            <fieldset className="flex min-w-0 flex-col gap-5" disabled={!canEdit}>
                <SeoCard title="Structured data" description="Helps Google understand the clinic, its providers and this page" actions={<Badge tone={problems === 0 ? 'success' : 'warning'} dot>{problems === 0 ? 'Valid' : `Valid · ${problems} ${problems === 1 ? 'note' : 'notes'}`}</Badge>}>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Select label="Page type" value={values.schema_type ?? ''} onChange={(event) => set('schema_type', event.target.value || null)} error={errors.schema_type}>
                            <option value="">{live.subject.default_schema_type} (default)</option>
                            {live.page_types.map((type) => (
                                <option key={type} value={type}>
                                    {type}
                                </option>
                            ))}
                        </Select>
                        <TextInput label="About" value={values.schema_about ?? ''} placeholder="e.g. Treatment-resistant depression" maxLength={120} onChange={(event) => set('schema_about', event.target.value || null)} help="The medical condition this page is about, if any" />
                    </div>
                </SeoCard>

                <SeoCard
                    title="On this page"
                    actions={
                        <Button variant="secondary" size="sm" icon={<Code2 className="size-4" aria-hidden="true" />} onClick={() => setCustom(values.schema_custom ? JSON.stringify(values.schema_custom, null, 2) : '{\n  "@type": ""\n}')}>
                            {values.schema_custom ? 'Edit custom JSON-LD' : 'Add custom JSON-LD'}
                        </Button>
                    }
                >
                    <ul className="-mx-6 flex flex-col divide-y divide-line border-t border-line">
                        {schema.pieces.map((piece) => {
                            const Icon = piece.key.startsWith('person_') ? Users : piece.key.startsWith('custom_') ? Code2 : (ICONS[piece.key] ?? HeartPulse);
                            const on = !disabled.includes(piece.key);

                            return (
                                <li key={piece.key} className="flex items-center gap-3.5 px-6 py-3.5">
                                    <span aria-hidden="true" className="flex size-9 shrink-0 items-center justify-center rounded-[10px] bg-sky-soft text-royal">
                                        <Icon className="size-[18px]" />
                                    </span>
                                    <div className="flex min-w-0 flex-1 flex-col">
                                        <span className="text-admin-body font-semibold text-ink">{piece.type}</span>
                                        <span className="truncate text-admin-small text-ink-soft">{piece.detail}</span>
                                    </div>
                                    <span className={cn('hidden shrink-0 rounded-full px-2.5 py-[3px] text-admin-small font-semibold sm:inline', SOURCE_TONE[piece.source] ?? 'bg-sky-soft text-royal')}>{piece.source}</span>
                                    {piece.key === 'webpage' ? (
                                        <span className="w-9 shrink-0" />
                                    ) : (
                                        <Toggle label={<span className="sr-only">Include {piece.type}</span>} checked={on} onChange={(event) => toggle(piece, event.target.checked)} />
                                    )}
                                </li>
                            );
                        })}
                    </ul>
                    {errors.schema_custom && <p className="text-admin-small text-danger">{errors.schema_custom}</p>}
                </SeoCard>
            </fieldset>

            <div className="flex min-w-0 flex-col gap-5 xl:sticky xl:top-6">
                <section className="flex min-w-0 flex-col overflow-hidden rounded-sheet bg-navy" aria-label="JSON-LD output">
                    <div className="flex items-center justify-between gap-3 border-b border-white/10 px-5 py-3.5">
                        <span className="text-admin-small font-semibold text-white">JSON-LD output</span>
                        <button
                            type="button"
                            className="text-admin-small font-semibold text-white/80 hover:text-white"
                            onClick={() => {
                                void navigator.clipboard?.writeText(json);
                                toast.success('JSON-LD copied');
                            }}
                        >
                            Copy
                        </button>
                    </div>
                    <pre className="max-h-[520px] overflow-auto px-5 py-4 font-mono text-[12px] leading-[1.6] break-all whitespace-pre-wrap text-white/90">
                        <code>{json}</code>
                    </pre>
                </section>
                <SeoCard
                    title="Validation"
                    description="Checked against schema.org and Google’s rules"
                    actions={
                        <a href={`https://search.google.com/test/rich-results?url=${encodeURIComponent(live.subject.url)}`} target="_blank" rel="noreferrer" className="inline-flex h-8 items-center gap-1.5 rounded-full border border-line px-3.5 font-display text-[13px] font-bold text-ink hover:border-ink-soft">
                            <ExternalLink className="size-3.5" aria-hidden="true" />
                            Rich Results Test
                        </a>
                    }
                >
                    <ul className="flex flex-col gap-2.5">
                        {schema.validation.map((item) => (
                            <li key={item.text} className="flex items-start gap-2.5 text-admin-body text-ink">
                                <span aria-hidden="true" className={cn('mt-[7px] size-2 shrink-0 rounded-full', STATUS_DOT[item.status])} />
                                {item.text}
                            </li>
                        ))}
                    </ul>
                </SeoCard>
            </div>

            <Dialog
                open={custom !== null}
                onClose={() => {
                    setCustom(null);
                    setCustomError(null);
                }}
                size="md"
                title="Custom JSON-LD"
                description="Added to this page’s graph as written. One object, or a list of objects, each with an @type."
                footer={
                    <>
                        {values.schema_custom && (
                            <Button
                                variant="ghost"
                                onClick={() => {
                                    set('schema_custom', null);
                                    setCustom(null);
                                }}
                            >
                                Remove
                            </Button>
                        )}
                        <Button onClick={saveCustom}>Use this JSON-LD</Button>
                    </>
                }
            >
                <Textarea
                    label="JSON"
                    rows={12}
                    spellCheck={false}
                    className="font-mono"
                    value={custom ?? ''}
                    onChange={(event) => {
                        setCustom(event.target.value);
                        setCustomError(null);
                    }}
                    error={customError ?? undefined}
                />
            </Dialog>
        </div>
    );
}
