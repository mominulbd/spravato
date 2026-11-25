import type { LucideIcon } from 'lucide-react';
import {
    AlignLeft,
    BookOpen,
    Check,
    CircleHelp,
    Columns2,
    CornerUpRight,
    FileText,
    GitCompare,
    HeartPulse,
    Image,
    LayoutGrid,
    LayoutTemplate,
    List,
    ListChecks,
    Map,
    Megaphone,
    Phone,
    Quote,
    Shield,
    Square,
    Stethoscope,
    Users,
} from 'lucide-react';

/** Lucide names sent by GET admin/pages/block-types → components (a static map keeps the bundle small). */
const ICONS: Record<string, LucideIcon> = {
    'align-left': AlignLeft,
    'book-open': BookOpen,
    check: Check,
    'help-circle': CircleHelp,
    columns: Columns2,
    'corner-up-right': CornerUpRight,
    'file-text': FileText,
    'git-compare': GitCompare,
    'heart-pulse': HeartPulse,
    image: Image,
    'layout-grid': LayoutGrid,
    'layout-template': LayoutTemplate,
    list: List,
    'list-checks': ListChecks,
    map: Map,
    megaphone: Megaphone,
    phone: Phone,
    quote: Quote,
    shield: Shield,
    stethoscope: Stethoscope,
    users: Users,
};

export function BlockIcon({ name, className = 'size-4' }: { name: string | undefined; className?: string }) {
    const Icon = (name && ICONS[name]) || Square;

    return <Icon className={className} aria-hidden="true" />;
}
