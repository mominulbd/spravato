import { useState } from 'react';
import { useNavigate } from 'react-router';
import { ApiError } from '@shared/api/client';
import { Button, Dialog, Select, TextInput } from '@shared/ui';
import { useCreatePage, usePages } from './api';
import { slugify } from './editorState';

/** "New page" (Figma 06): a draft built from a template's starter sections. */
export function NewPageDialog({ open, onClose, templates }: { open: boolean; onClose: () => void; templates: { value: string; label: string }[] }) {
    const navigate = useNavigate();
    const create = useCreatePage();
    const parents = usePages({ per_page: 100, sort: 'path' });
    const [title, setTitle] = useState('');
    const [slug, setSlug] = useState('');
    const [slugEdited, setSlugEdited] = useState(false);
    const [template, setTemplate] = useState('standard');
    const [parent, setParent] = useState('');
    const [errors, setErrors] = useState<Record<string, string[]>>({});
    const [general, setGeneral] = useState<string | null>(null);

    const close = () => {
        setTitle('');
        setSlug('');
        setSlugEdited(false);
        setParent('');
        setErrors({});
        setGeneral(null);
        onClose();
    };

    const parentPage = parents.data?.pages.find((page) => String(page.id) === parent);
    const prefix = parentPage && !parentPage.is_home ? parentPage.path : '';

    const submit = () => {
        create.mutate(
            { title: title.trim(), slug, template, parent_id: parent === '' ? null : Number(parent) },
            {
                onSuccess: (response) => {
                    close();
                    navigate(`/pages/${response.data.id}`);
                },
                onError: (error) => {
                    if (error instanceof ApiError && error.code === 'VALIDATION_FAILED') {
                        setErrors(error.fieldErrors);
                        setGeneral(null);
                    } else {
                        setGeneral(error instanceof ApiError ? error.humanMessage : 'That didn’t work. Try again.');
                    }
                },
            },
        );
    };

    return (
        <Dialog
            open={open}
            onClose={close}
            size="md"
            title="New page"
            description="Pages start as drafts with the template’s sections. Nothing appears on the website until you publish."
            dismissible={!create.isPending}
            footer={
                <>
                    <Button variant="ghost" onClick={close} disabled={create.isPending}>
                        Cancel
                    </Button>
                    <Button type="submit" form="new-page-form" loading={create.isPending} disabled={title.trim() === '' || slug === ''}>
                        Create draft
                    </Button>
                </>
            }
        >
            <form
                id="new-page-form"
                className="flex flex-col gap-4"
                onSubmit={(event) => {
                    event.preventDefault();
                    submit();
                }}
            >
                <TextInput
                    label="Title"
                    value={title}
                    maxLength={120}
                    autoFocus
                    error={errors.title?.[0]}
                    onChange={(event) => {
                        setTitle(event.target.value);
                        if (!slugEdited) setSlug(slugify(event.target.value));
                    }}
                />
                <TextInput
                    label="Address"
                    value={slug}
                    maxLength={80}
                    help={`uptms.com${prefix}/${slug || 'page-address'}`}
                    error={errors.slug?.[0]}
                    onChange={(event) => {
                        setSlugEdited(true);
                        setSlug(slugify(event.target.value));
                    }}
                />
                <div className="grid gap-4 sm:grid-cols-2">
                    <Select label="Template" value={template} error={errors.template?.[0]} onChange={(event) => setTemplate(event.target.value)}>
                        {templates.map((option) => (
                            <option key={option.value} value={option.value}>
                                {option.label}
                            </option>
                        ))}
                    </Select>
                    <Select label="Parent page" value={parent} error={errors.parent_id?.[0]} onChange={(event) => setParent(event.target.value)}>
                        <option value="">None (top level)</option>
                        {(parents.data?.pages ?? [])
                            .filter((page) => !page.is_home)
                            .map((page) => (
                                <option key={page.id} value={page.id}>
                                    {page.title} ({page.path})
                                </option>
                            ))}
                    </Select>
                </div>
                {general && (
                    <p role="alert" className="rounded-[12px] bg-danger-soft px-4 py-3 text-admin-body text-danger">
                        {general}
                    </p>
                )}
            </form>
        </Dialog>
    );
}
