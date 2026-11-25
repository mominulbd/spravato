import { Check, Undo2 } from 'lucide-react';
import { ApiError } from '@shared/api/client';
import { can } from '@shared/auth/can';
import { Button, ErrorState, Skeleton, useToast } from '@shared/ui';
import { useCurrentUser } from '@admin/app/AuthProvider';
import { useSeo } from './api';
import { SchemaPanel } from './SchemaPanel';
import { SeoPanel } from './SeoPanel';
import { SocialPanel } from './SocialPanel';
import type { SeoPayload } from './types';
import { useSeoDraft } from './useSeoDraft';

export type SeoTabKey = 'seo' | 'social' | 'schema';

/**
 * The SEO, Social and Schema tabs of any editor (pages, posts, conditions).
 * Stays mounted while switching between the three tabs so unsaved edits
 * survive. Saving makes the values live at once (seo.edit).
 */
export function SeoWorkspace({ type, id, tab }: { type: string; id: number; tab: SeoTabKey }) {
    const seo = useSeo(type, id);

    if (seo.isPending) {
        return (
            <div className="grid gap-5 xl:grid-cols-[minmax(0,1fr)_340px]" aria-busy="true" aria-label="Loading SEO">
                <Skeleton className="h-[560px] rounded-sheet" />
                <Skeleton className="h-[360px] rounded-sheet" />
            </div>
        );
    }

    if (seo.isError) {
        return <ErrorState title="SEO didn’t load" description={seo.error instanceof ApiError ? seo.error.humanMessage : 'Try again in a moment.'} onRetry={() => void seo.refetch()} />;
    }

    return <Loaded type={type} id={id} tab={tab} saved={seo.data} />;
}

function Loaded({ type, id, tab, saved }: { type: string; id: number; tab: SeoTabKey; saved: SeoPayload }) {
    const user = useCurrentUser();
    const toast = useToast();
    const draft = useSeoDraft(type, id, saved);
    const canEdit = can(user, 'seo.edit');

    const save = async () => {
        try {
            await draft.save();
            toast.success('SEO saved', 'The website updates within seconds.');
        } catch (error) {
            toast.error('SEO not saved', error instanceof ApiError ? error.humanMessage : undefined);
        }
    };

    return (
        <div className="flex flex-col gap-5">
            <div className="flex flex-wrap items-center justify-between gap-3 rounded-card border border-line bg-white px-5 py-3">
                <p className="text-admin-small text-ink-soft" aria-live="polite">
                    {!canEdit
                        ? 'You can view SEO. Changing it needs the “SEO fields on pages” permission.'
                        : draft.dirty
                          ? 'Unsaved SEO changes. They go live on the website when you save.'
                          : 'SEO changes go live as soon as they’re saved; they don’t wait for Publish.'}
                </p>
                {canEdit && (
                    <div className="flex items-center gap-2">
                        {draft.dirty && (
                            <Button variant="ghost" size="sm" icon={<Undo2 className="size-4" aria-hidden="true" />} onClick={draft.discard}>
                                Discard
                            </Button>
                        )}
                        <Button size="sm" icon={<Check className="size-4" aria-hidden="true" />} loading={draft.saving} disabled={!draft.dirty} onClick={() => void save()}>
                            Save SEO
                        </Button>
                    </div>
                )}
            </div>
            {tab === 'seo' && <SeoPanel draft={draft} canEdit={canEdit} />}
            {tab === 'social' && <SocialPanel draft={draft} canEdit={canEdit} />}
            {tab === 'schema' && <SchemaPanel draft={draft} canEdit={canEdit} />}
        </div>
    );
}
