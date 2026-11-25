/** Shapes of the public API (docs/API.md), as the site reads them. */

export interface Link {
  label: string;
  href: string;
}

export interface ImageSource {
  width: number;
  url: string;
}

export interface PublicImage {
  id: number;
  url: string;
  width: number | null;
  height: number | null;
  alt: string;
  focal: { x: number; y: number };
  sources: Partial<Record<"avif" | "webp", ImageSource[]>>;
}

export interface Block<D = Record<string, unknown>, S = Record<string, unknown>> {
  id: number | null;
  type: string;
  anchor: string | null;
  number: string | null;
  data: D;
  sources: S;
}

export interface PublicPage {
  id: number;
  title: string;
  path: string;
  blocks: Block[];
  seo: { title?: string; description?: string } & Record<string, unknown>;
}

export interface Settings {
  clinic: {
    name: string;
    tagline: string | null;
    service_area_label: string;
    phone: { display: string; href: string };
    email: string;
    address: { street: string; locality: string; region: string; postal_code: string; single_line: string };
  };
  hours: { short: string; long: string };
  legal: { copyright: string; crisis_short: string };
}

export interface MenuItem {
  label: string;
  href: string;
  children?: MenuItem[];
}

export type Menus = Record<string, MenuItem[]>;

/** What every block component receives besides its own block. */
export interface SiteContext {
  settings: Settings;
  menus: Menus;
}

export interface TeamMember {
  name: string;
  credentials: string | null;
  role_title: string;
  bio: string | null;
  photo: PublicImage | null;
  is_founder: boolean;
}

export interface Faq {
  question: string;
  answer: string;
}
