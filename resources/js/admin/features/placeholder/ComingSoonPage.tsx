import { Clock } from 'lucide-react';
import { ButtonLink, Card, EmptyState } from '@shared/ui';
import type { NavItem } from '@admin/app/navigation';
import { PageHeader } from '@admin/components/PageHeader';

/** Screens designed in Figma but scheduled for a later phase (docs/ROADMAP.md). */
export function ComingSoonPage({ item, group }: { item: NavItem; group: string }) {
    return (
        <>
            <PageHeader trail={[group, item.label]} title={item.label} />
            <Card>
                <EmptyState
                    icon={<Clock className="size-5" aria-hidden="true" />}
                    title={`${item.label} arrives in Phase ${item.phase}`}
                    description="The design is ready; this screen is built in a later phase of the roadmap."
                    action={
                        <ButtonLink to="/" variant="secondary" size="sm">
                            Back to dashboard
                        </ButtonLink>
                    }
                />
            </Card>
        </>
    );
}
