import type { LucideIcon } from 'lucide-react';
import {
    AlignLeft,
    BookOpen,
    Calendar,
    Check,
    CircleHelp,
    Columns2,
    CornerUpRight,
    FileText,
    Footprints,
    GitCompare,
    HeartPulse,
    Image,
    LayoutGrid,
    LayoutTemplate,
    List,
    ListChecks,
    Map,
    MapPin,
    Megaphone,
    Phone,
    Play,
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
    calendar: Calendar,
    check: Check,
    'help-circle': CircleHelp,
    columns: Columns2,
    'corner-up-right': CornerUpRight,
    'file-text': FileText,
    footprints: Footprints,
    'git-compare': GitCompare,
    'heart-pulse': HeartPulse,
    image: Image,
    'layout-grid': LayoutGrid,
    'layout-template': LayoutTemplate,
    list: List,
    'list-checks': ListChecks,
    map: Map,
    'map-pin': MapPin,
    megaphone: Megaphone,
    phone: Phone,
    play: Play,
    quote: Quote,
    shield: Shield,
    stethoscope: Stethoscope,
    users: Users,
};

export function BlockIcon({ name, className = 'size-4' }: { name: string | undefined; className?: string }) {
    const Icon = (name && ICONS[name]) || Square;

    return <Icon className={className} aria-hidden="true" />;
}
