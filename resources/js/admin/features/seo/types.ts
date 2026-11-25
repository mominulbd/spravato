/** Shapes of /api/v1/admin/seo/{type}/{id} (docs/API.md §9). */
export type CheckStatus = 'good' | 'improve' | 'problem';

export interface Check {
    key: string;
    status: CheckStatus;
    text: string;
}

export interface Citation {
    label: string;
    url: string;
}

export interface SeoValues {
    meta_title: string | null;
    meta_description: string | null;
    focus_keyphrase: string | null;
    keyphrases: string[] | null;
    canonical_url: string | null;
    robots_index: boolean;
    robots_follow: boolean;
    in_sitemap: boolean;
    breadcrumb_title: string | null;
    max_image_preview: 'large' | 'standard' | 'none';
    max_snippet: number | null;
    og_custom: boolean;
    og_title: string | null;
    og_description: string | null;
    og_image_id: number | null;
    twitter_card: 'summary' | 'summary_large_image';
    schema_type: string | null;
    schema_about: string | null;
    schema_disabled: string[] | null;
    schema_custom: Record<string, unknown> | Record<string, unknown>[] | null;
    reviewed_by: number | null;
    last_reviewed_at: string | null;
    citations: Citation[] | null;
}

export interface SchemaPiece {
    key: string;
    type: string;
    label: string;
    detail: string;
    source: string;
    enabled: boolean;
}

export interface SeoPayload {
    subject: {
        type: string;
        id: number;
        label: string;
        title: string;
        path: string;
        url: string;
        is_home: boolean;
        published: boolean;
        template: string;
        template_key: string;
        default_schema_type: string;
    };
    meta: SeoValues;
    resolved: {
        title: string;
        description: string;
        default_title: string;
        default_description: string;
        canonical: string;
        share_title: string;
        share_description: string;
        share_image: { url: string; width: number | null; height: number | null; alt: string } | null;
        site_name: string;
        site_host: string;
        x_handle: string;
    };
    analysis: {
        score: number;
        readability: number;
        seo: Check[];
        readability_checks: Check[];
        title: { chars: number; px: number; max_px: number };
        description: { chars: number; px: number; max_px: number };
        keyphrase_uses: number;
    };
    schema: { graph: Record<string, unknown>[]; pieces: SchemaPiece[]; validation: { status: CheckStatus; text: string }[] };
    links: { incoming: { label: string; path: string }[]; suggestions: { title: string; path: string; reason: string }[] };
    page_types: string[];
    reviewers: { id: number; name: string }[];
    analyzed_at: string | null;
}

/** Figma 19: one row of the site audit. */
export type IssueSeverity = 'urgent' | 'warning' | 'notice' | 'passed';

export interface AuditIssue {
    key: string;
    severity: IssueSeverity;
    title: string;
    detail: string;
    count: number;
    items: { key: string; label: string; path: string | null; admin_path: string | null }[];
    action: { label: string; to: string } | null;
}

export interface VitalMetric {
    key: 'lcp' | 'inp' | 'cls';
    label: string;
    value: number;
    unit: string;
    rating: 'good' | 'needs_improvement' | 'poor';
    good: number;
    poor: number;
}

export interface SeoAuditOverview {
    audit: {
        id: number;
        score: number | null;
        previous_score: number | null;
        urls: number;
        indexable: number;
        indexed: number | null;
        trigger: 'schedule' | 'manual';
        started_at: string;
        finished_at: string | null;
    } | null;
    running: boolean;
    issues: AuditIssue[];
    open: number;
    fixed_this_week: number;
    search: { clicks: number; impressions: number; position: number | null; queries: { query: string; clicks: number; position: number }[] } | null;
    vitals: { passing: boolean; metrics: VitalMetric[] } | null;
    connections: { search_console: boolean; core_web_vitals: boolean };
    site_host: string;
}
