import { can } from '@shared/auth/can';
import { useCurrentUser } from '@admin/app/AuthProvider';

/**
 * The publish rule of CollectionItemPolicy, for hiding controls: anything
 * the website shows (published items, the Published switch, the order of a
 * list) needs content.publish; hidden drafts need content.edit.
 */
export function useCollectionAccess() {
    const user = useCurrentUser();
    const mayEdit = can(user, 'content.edit');
    const mayPublish = can(user, 'content.publish');

    return {
        mayEdit,
        mayPublish,
        mayChange: (item: { is_published: boolean } | null) => mayEdit && (item === null || !item.is_published || mayPublish),
    };
}
