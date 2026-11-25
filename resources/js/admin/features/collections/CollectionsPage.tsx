import { useState } from 'react';
import { Navigate, useParams } from 'react-router';
import { Plus } from 'lucide-react';
import { Button, ErrorState, Skeleton, TabLinks } from '@shared/ui';
import { PageHeader } from '@admin/components/PageHeader';
import { useCollectionTabs } from './api';
import { FaqPanel } from './FaqPanel';
import { LIST_SPECS } from './listFields';
import { ListPanel } from './ListPanel';
import { useCollectionAccess } from './permissions';
import { TeamPanel } from './TeamPanel';
import type { CollectionKey } from './types';

const NEW_LABEL: Record<CollectionKey, string> = {
    team: 'New team member',
    faqs: 'New question',
    testimonials: 'New testimonial',
    insurance: 'New partner',
    'trust-points': 'New trust point',
    'journey-steps': 'New step',
};

/** Figma 14–15: reusable content, one tab per collection. */
export function CollectionsPage() {
    const { type } = useParams();
    const tabs = useCollectionTabs();
    const access = useCollectionAccess();
    const [creating, setCreating] = useState<string | null>(null);
    const key = (type ?? 'team') as CollectionKey;

    if (tabs.data && !tabs.data.some((tab) => tab.key === key)) {
        return <Navigate to="/collections/team" replace />;
    }

    const isCreating = creating === key;
    const done = () => setCreating(null);
    const spec = LIST_SPECS[key];

    return (
        <div className="flex flex-col gap-7">
            <PageHeader
                trail={['Content', 'Collections']}
                title=""
                accent="Collections."
                description="Reusable content shown across the website. Edit it once here and every page that uses it updates."
                actions={
                    access.mayEdit && (
                        <Button icon={<Plus className="size-4" />} onClick={() => setCreating(key)} disabled={isCreating}>
                            {NEW_LABEL[key]}
                        </Button>
                    )
                }
            />

            {tabs.isPending ? (
                <Skeleton className="h-11 w-full" />
            ) : tabs.isError ? (
                <ErrorState title="Collections didn’t load" description="Check your connection and try again." onRetry={() => void tabs.refetch()} />
            ) : (
                <TabLinks
                    label="Collections"
                    items={tabs.data.map((tab) => ({
                        to: `/collections/${tab.key}`,
                        label: tab.label,
                        count: tab.count,
                    }))}
                />
            )}

            {key === 'team' ? (
                <TeamPanel creating={isCreating} onCreated={done} />
            ) : key === 'faqs' ? (
                <FaqPanel creating={isCreating} onCreated={done} />
            ) : spec ? (
                <ListPanel key={key} type={key} spec={spec} creating={isCreating} onCreated={done} />
            ) : null}
        </div>
    );
}
