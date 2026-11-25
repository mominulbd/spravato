import type { LucideIcon } from 'lucide-react';
import {
    Building2,
    CornerUpRight,
    FileText,
    Globe,
    History,
    Image,
    Inbox,
    KeyRound,
    LayoutGrid,
    LayoutTemplate,
    Map,
    Menu,
    TrendingUp,
    TriangleAlert,
} from 'lucide-react';

/**
 * The sidebar of Figma Admin/Sidebar (54:22). Items appear only when the
 * user holds one of `permissions` (UI only — the API enforces). `phase`
 * marks screens still to be built; they open a "coming in Phase N" page.
 */
export interface NavItem {
    label: string;
    to: string;
    icon: LucideIcon;
    permissions: string[];
    phase?: number;
    /** A live count beside the label (Figma: new requests waiting). */
    badge?: 'consultations';
}

export interface NavGroup {
    label: string;
    items: NavItem[];
}

export const NAVIGATION: NavGroup[] = [
    {
        label: 'Overview',
        items: [
            { label: 'Dashboard', to: '/', icon: LayoutGrid, permissions: ['admin.access'] },
            { label: 'Consultations', to: '/consultations', icon: Inbox, permissions: ['consultations.view'], badge: 'consultations' },
        ],
    },
    {
        label: 'Content',
        items: [
            { label: 'Pages', to: '/pages', icon: FileText, permissions: ['content.edit'] },
            { label: 'Collections', to: '/collections', icon: LayoutTemplate, permissions: ['content.edit'] },
            { label: 'Navigation menus', to: '/menus', icon: Menu, permissions: ['content.edit'] },
            { label: 'Media library', to: '/media', icon: Image, permissions: ['media.manage', 'content.edit'] },
        ],
    },
    {
        label: 'SEO',
        items: [
            { label: 'SEO overview', to: '/seo', icon: TrendingUp, permissions: ['seo.edit', 'seo.manage'] },
            { label: 'Redirects', to: '/seo/redirects', icon: CornerUpRight, permissions: ['seo.manage'] },
            { label: '404 monitor', to: '/seo/404', icon: TriangleAlert, permissions: ['seo.manage'] },
            { label: 'Sitemap & robots', to: '/seo/sitemap', icon: Map, permissions: ['seo.manage'] },
            { label: 'SEO settings', to: '/seo/settings', icon: Globe, permissions: ['seo.manage'] },
        ],
    },
    {
        label: 'System',
        items: [
            { label: 'Clinic settings', to: '/settings', icon: Building2, permissions: ['settings.manage'] },
            { label: 'Users & roles', to: '/users', icon: KeyRound, permissions: ['users.manage', 'roles.manage'] },
            { label: 'Audit log', to: '/audit-log', icon: History, permissions: ['audit.view'] },
        ],
    },
];

/** Groups with only the items `permissions` allow; empty groups drop out. */
export function visibleNavigation(permissions: string[]): NavGroup[] {
    return NAVIGATION.map((group) => ({
        ...group,
        items: group.items.filter((item) => item.permissions.some((key) => permissions.includes(key))),
    })).filter((group) => group.items.length > 0);
}

/** Every placeholder route, for the router. */
export const PLANNED_ROUTES = NAVIGATION.flatMap((group) => group.items).filter((item) => item.phase !== undefined);
