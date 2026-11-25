import { useState } from 'react';
import { TriangleAlert } from 'lucide-react';
import { ApiError } from '@shared/api/client';
import { Button, Select, TextInput, useToast } from '@shared/ui';
import { pageRequests, usePages } from './api';
import { slugify } from './editorState';
import type { Editor } from './useEditor';

/** Title, address, template and parent. Moving a live page changes its URL at once and leaves a 301 behind. */
export function SettingsTab({ editor, canChange }: { editor: Editor; canChange: boolean }) {
    const toast = useToast();
    const { meta } = editor;
    const list = usePages({ per_page: 100, sort: 'path' });
    const [title, setTitle] = useState(meta.title);
    const [slug, setSlug] = useState(meta.slug);
    const [template, setTemplate] = useState(meta.template);
    const [parent, setParent] = useState(meta.parent_id === null ? '' : String(meta.parent_id));
    const [errors, setErrors] = useState<Record<string, string[]>>({});
    const [busy, setBusy] = useState(false);
    const moving = slug !== meta.slug || (parent === '' ? null : Number(parent)) !== meta.parent_id;
    const parentPage = list.data?.pages.find((page) => String(page.id) === parent);
    const prefix = parentPage && !parentPage.is_home ? parentPage.path : '';

    const save = async () => {
        setBusy(true);

        try {
            const response = await pageRequests.saveSettings(meta.id, { title: title.trim(), slug, template, parent_id: parent === '' ? null : Number(parent) });
            editor.applyPage(response.data, false);
            setErrors({});
            toast.success('Page settings saved', response.data.path);
        } catch (error) {
            if (error instanceof ApiError && error.code === 'VALIDATION_FAILED') setErrors(error.fieldErrors);
            else toast.error('Settings not saved', error instanceof ApiError ? error.humanMessage : undefined);
        } finally {
            setBusy(false);
        }
    };

    return (
        <section aria-labelledby="settings-title" className="flex max-w-[720px] flex-col gap-5 rounded-sheet border border-line bg-white p-6 sm:p-8">
            <div>
                <h2 id="settings-title" className="font-serif text-admin-section-title text-ink">
                    Page settings
                </h2>
                <p className="mt-1 text-admin-body text-ink-soft">The title shows in the admin and menus. Templates only change which sections new pages start with.</p>
            </div>
            <TextInput label="Title" value={title} maxLength={120} disabled={!canChange} error={errors.title?.[0]} onChange={(event) => setTitle(event.target.value)} />
            <TextInput
                label="Address"
                value={slug}
                maxLength={80}
                disabled={!canChange || meta.is_home}
                help={meta.is_home ? 'The home page always lives at /.' : `uptms.com${prefix}/${slug}`}
                error={errors.slug?.[0]}
                onChange={(event) => setSlug(slugify(event.target.value))}
            />
            <div className="grid gap-4 sm:grid-cols-2">
                <Select label="Template" value={template} disabled={!canChange} error={errors.template?.[0]} onChange={(event) => setTemplate(event.target.value)}>
                    {(list.data?.meta.templates ?? [{ value: meta.template, label: meta.template_label }]).map((option) => (
                        <option key={option.value} value={option.value}>
                            {option.label}
                        </option>
                    ))}
                </Select>
                <Select label="Parent page" value={parent} disabled={!canChange || meta.is_home} error={errors.parent_id?.[0]} onChange={(event) => setParent(event.target.value)}>
                    <option value="">None (top level)</option>
                    {(list.data?.pages ?? [])
                        .filter((page) => !page.is_home && page.id !== meta.id)
                        .map((page) => (
                            <option key={page.id} value={page.id}>
                                {page.title} ({page.path})
                            </option>
                        ))}
                </Select>
            </div>
            {moving && meta.status === 'published' && (
                <p role="note" className="flex items-start gap-2 rounded-[14px] bg-warning-soft px-4 py-3 text-admin-body text-warning">
                    <TriangleAlert className="mt-0.5 size-4 shrink-0" aria-hidden="true" />
                    This page is live. Saving moves it to the new address at once, and {meta.path} redirects there (301) so links and search results keep working. Menus follow automatically.
                </p>
            )}
            {!canChange && <p className="text-admin-small text-ink-soft">Changing a published page’s settings needs the Publish permission.</p>}
            <div>
                <Button onClick={() => void save()} loading={busy} disabled={!canChange || title.trim() === '' || slug === ''}>
                    Save settings
                </Button>
            </div>
        </section>
    );
}
