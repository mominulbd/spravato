<?php

declare(strict_types=1);

namespace Uptms\Seo\Services;

use Illuminate\Support\Str;
use Uptms\Media\Services\MediaPresenter;
use Uptms\Seo\Domain\SeoSubject;
use Uptms\Seo\Models\SeoMeta;
use Uptms\Settings\Services\PublicSettings;

/**
 * The JSON-LD graph of Figma 10: the clinic (from Clinic settings), the
 * website, this page, its breadcrumbs, and what the page itself holds —
 * FAQs, clinicians or an article. Each piece can be switched off per page;
 * staff may append custom nodes (the Spravato page adds its Drug node so).
 */
final class SchemaGraph
{
    public const array PAGE_TYPES = ['WebPage', 'MedicalWebPage', 'AboutPage', 'ContactPage', 'CollectionPage', 'FAQPage', 'Article'];

    /** @var array<string, mixed>|null */
    private ?array $settings = null;

    public function __construct(
        private readonly PublicSettings $publicSettings,
        private readonly SeoSettings $seo,
        private readonly MediaPresenter $media,
        private readonly Reviewers $reviewers,
    ) {}

    /**
     * @return array{graph: list<array<string, mixed>>, pieces: list<array{key: string, type: string, label: string, detail: string, source: string, enabled: bool}>, validation: list<array{status: string, text: string}>}
     */
    public function build(SeoSubject $subject, SeoMeta $meta, string $title, string $description, ?string $imageUrl): array
    {
        $base = self::siteUrl();
        $url = self::absolute($subject->path);
        $clinic = $this->clinic();
        $disabled = $meta->schema_disabled ?? [];
        $pageType = in_array($meta->schema_type, self::PAGE_TYPES, true) ? (string) $meta->schema_type : $subject->defaultSchemaType;
        $pieces = [];
        $graph = [];
        $add = function (string $key, string $label, string $detail, string $source, ?array $node) use (&$pieces, &$graph, $disabled): void {
            if ($node === null) {
                return;
            }
            $enabled = ! in_array($key, $disabled, true);
            $pieces[] = ['key' => $key, 'type' => is_array($node['@type']) ? implode(', ', $node['@type']) : (string) $node['@type'], 'label' => $label, 'detail' => $detail, 'source' => $source, 'enabled' => $enabled];
            if ($enabled) {
                $graph[] = $node;
            }
        };

        $add('clinic', (string) $clinic['@type'], (string) $clinic['name'], 'From clinic settings', $clinic);
        $add('website', 'WebSite', (string) parse_url($base, PHP_URL_HOST), 'Site-wide', [
            '@type' => 'WebSite',
            '@id' => $base.'/#website',
            'url' => $base.'/',
            'name' => $clinic['name'],
            'publisher' => ['@id' => $base.'/#clinic'],
            'inLanguage' => 'en-US',
        ]);

        $reviewer = $subject->article['reviewer'] ?? $subject->review['name'] ?? $this->reviewers->name($meta->reviewed_by);
        $reviewedAt = $subject->article['reviewed_at'] ?? $subject->review['date'] ?? $meta->last_reviewed_at?->toDateString();
        $about = $meta->schema_about !== null && $meta->schema_about !== '' ? $meta->schema_about : $subject->about;
        $page = array_filter([
            '@type' => $pageType === 'Article' ? 'WebPage' : $pageType,
            '@id' => $url.'#webpage',
            'url' => $url,
            'name' => $title,
            'description' => $description !== '' ? $description : null,
            'isPartOf' => ['@id' => $base.'/#website'],
            'about' => $about !== null ? ['@type' => 'MedicalCondition', 'name' => $about] : ['@id' => $base.'/#clinic'],
            'breadcrumb' => ['@id' => $url.'#breadcrumb'],
            'primaryImageOfPage' => $imageUrl !== null ? ['@type' => 'ImageObject', 'url' => $imageUrl] : null,
            'datePublished' => $subject->publishedAt?->toIso8601String(),
            'dateModified' => $subject->updatedAt?->toIso8601String(),
            'lastReviewed' => $reviewedAt,
            'reviewedBy' => $reviewer !== null ? ['@type' => 'Person', 'name' => $reviewer] : null,
            'citation' => $meta->citations !== null && $meta->citations !== [] ? array_map(fn (array $c): array => ['@type' => 'CreativeWork', 'name' => $c['label'], 'url' => $c['url']], $meta->citations) : null,
            'inLanguage' => 'en-US',
        ], fn (mixed $v): bool => $v !== null);
        $add('webpage', (string) $page['@type'], 'This page'.($reviewer !== null && $reviewedAt !== null ? ' · reviewed by '.$reviewer.' on '.$reviewedAt : ''), 'This page', $page);

        $crumbs = [['name' => 'Home', 'path' => '/'], ...$subject->breadcrumbs];
        if (! $subject->isHome) {
            $crumbs[] = ['name' => $meta->breadcrumb_title ?: $subject->title, 'path' => $subject->path];
        }
        $add('breadcrumbs', 'BreadcrumbList', implode(' › ', array_column($crumbs, 'name')), 'Automatic', [
            '@type' => 'BreadcrumbList',
            '@id' => $url.'#breadcrumb',
            'itemListElement' => array_map(fn (array $crumb, int $i): array => [
                '@type' => 'ListItem',
                'position' => $i + 1,
                'name' => $crumb['name'],
                'item' => self::absolute($crumb['path']),
            ], $crumbs, array_keys($crumbs)),
        ]);

        if ($subject->article !== null) {
            $add('article', 'Article', $subject->title, 'This post', array_filter([
                '@type' => 'Article',
                '@id' => $url.'#article',
                'headline' => mb_substr($subject->title, 0, 110),
                'description' => $description !== '' ? $description : null,
                'image' => $imageUrl,
                'author' => $subject->article['author'] !== null
                    ? array_filter(['@type' => 'Person', 'name' => $subject->article['author'], 'honorificSuffix' => $subject->article['author_credentials']])
                    : ['@id' => $base.'/#clinic'],
                'publisher' => ['@id' => $base.'/#clinic'],
                'datePublished' => $subject->publishedAt?->toIso8601String(),
                'dateModified' => $subject->updatedAt?->toIso8601String(),
                'articleSection' => $subject->article['section'],
                'mainEntityOfPage' => ['@id' => $url.'#webpage'],
                'wordCount' => $subject->wordCount(),
            ], fn (mixed $v): bool => $v !== null));
        }

        if ($subject->faqs !== []) {
            $add('faq', 'FAQPage', count($subject->faqs).' questions from the FAQ section', 'From FAQ section', [
                '@type' => 'FAQPage',
                '@id' => $url.'#faq',
                'mainEntity' => array_map(fn (array $faq): array => [
                    '@type' => 'Question',
                    'name' => $faq['question'],
                    'acceptedAnswer' => ['@type' => 'Answer', 'text' => $faq['answer']],
                ], $subject->faqs),
            ]);
        }

        foreach ($subject->people as $index => $person) {
            // schema.org's Physician is a medical organization, so a clinician
            // marked Physician is typed both ways to keep Person properties valid.
            $type = $person['schema_type'] === 'Physician' ? ['Person', 'IndividualPhysician'] : 'Person';
            $add('person_'.$index, $person['schema_type'], trim($person['name'].($person['credentials'] ? ', '.$person['credentials'] : '')), 'From team', array_filter([
                '@type' => $type,
                '@id' => $base.'/#person-'.Str::slug($person['name']),
                'name' => $person['name'],
                'honorificSuffix' => $person['credentials'],
                'jobTitle' => $person['role'],
                'image' => $person['image'],
                'worksFor' => ['@id' => $base.'/#clinic'],
            ], fn (mixed $v): bool => $v !== null && $v !== ''));
        }

        foreach (self::customNodes($meta->schema_custom) as $index => $node) {
            $add('custom_'.$index, (string) ($node['@type'] ?? 'Thing'), 'Custom JSON-LD', 'Custom', $node);
        }

        return ['graph' => $graph, 'pieces' => $pieces, 'validation' => $this->validate($graph, $clinic)];
    }

    /** @return array<string, mixed> the MedicalClinic node */
    public function clinic(): array
    {
        $settings = $this->settings ??= $this->publicSettings->toArray();
        /** @var array<string, mixed> $clinic */
        $clinic = $settings['clinic'];
        /** @var array<string, mixed> $hours */
        $hours = $settings['hours'];
        $base = self::siteUrl();
        $logo = $this->media->present(is_int($this->seo->get('logo_id')) ? $this->seo->get('logo_id') : null);
        $address = $clinic['address'];
        $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

        return array_filter([
            '@type' => (string) ($this->seo->get('schema_type') ?: 'MedicalClinic'),
            '@id' => $base.'/#clinic',
            'name' => $clinic['name'],
            'url' => $base.'/',
            'telephone' => self::e164($clinic['phone']['display'] ?? ''),
            'email' => $clinic['email'],
            'address' => [
                '@type' => 'PostalAddress',
                'streetAddress' => $address['street'],
                'addressLocality' => $address['locality'],
                'addressRegion' => $address['region'],
                'postalCode' => $address['postal_code'],
                'addressCountry' => $address['country'],
            ],
            'geo' => $clinic['geo'] !== null ? ['@type' => 'GeoCoordinates', 'latitude' => $clinic['geo']['lat'], 'longitude' => $clinic['geo']['lng']] : null,
            'openingHoursSpecification' => array_values(array_map(fn (array $day): array => [
                '@type' => 'OpeningHoursSpecification',
                'dayOfWeek' => $days[$day['day'] - 1],
                'opens' => $day['opens_at'],
                'closes' => $day['closes_at'],
            ], array_filter($hours['days'], fn (array $day): bool => $day['is_open'] && $day['opens_at'] !== null && $day['closes_at'] !== null))),
            'medicalSpecialty' => $this->seo->get('medical_specialty') ?: null,
            'priceRange' => $this->seo->get('price_range') ?: null,
            'logo' => $logo['url'] ?? null,
            'image' => $logo['url'] ?? null,
            'areaServed' => array_map(fn (string $place): array => ['@type' => 'City', 'name' => $place], $clinic['service_area']),
            'sameAs' => array_values(array_filter(array_map(fn (array $s): ?string => $s['url'] ?? null, $clinic['social']))) ?: null,
        ], fn (mixed $v): bool => $v !== null && $v !== '' && $v !== []);
    }

    /**
     * @param  list<array<string, mixed>>  $graph
     * @param  array<string, mixed>  $clinic
     * @return list<array{status: string, text: string}>
     */
    private function validate(array $graph, array $clinic): array
    {
        $errors = [];

        foreach ($graph as $node) {
            if (! isset($node['@type']) || ! is_string($node['@type'])) {
                $errors[] = 'A node has no @type.';
            }
            if (($node['@type'] ?? null) === 'Article' && ! isset($node['image'])) {
                $errors[] = 'The article has no image; Google requires one for article rich results.';
            }
        }

        $checks = [
            $errors === []
                ? ['status' => 'good', 'text' => '0 errors in '.count($graph).' items']
                : ['status' => 'problem', 'text' => count($errors).' '.(count($errors) === 1 ? 'error' : 'errors').': '.implode(' ', $errors)],
            isset($clinic['telephone'], $clinic['address'])
                ? ['status' => 'good', 'text' => 'Address and phone come from Clinic settings']
                : ['status' => 'problem', 'text' => 'Add the clinic’s phone and address in Clinic settings'],
        ];

        if (collect($graph)->contains(fn (array $n): bool => ($n['@type'] ?? null) === 'FAQPage')) {
            $checks[] = ['status' => 'improve', 'text' => 'FAQ rich results only appear for well-known government and health sites; the markup still helps Google understand the page.'];
        }

        return $checks;
    }

    /**
     * Staff-added nodes: an object or a list of objects, each with an @type.
     *
     * @return list<array<string, mixed>>
     */
    public static function customNodes(mixed $custom): array
    {
        if (! is_array($custom) || $custom === []) {
            return [];
        }

        $nodes = array_is_list($custom) ? $custom : [$custom];

        return array_values(array_filter($nodes, fn (mixed $node): bool => is_array($node) && is_string($node['@type'] ?? null)));
    }

    public static function siteUrl(): string
    {
        return rtrim((string) config('services.frontend.url'), '/');
    }

    public static function absolute(string $path): string
    {
        return self::siteUrl().($path === '/' ? '/' : '/'.ltrim($path, '/'));
    }

    /** "253-234-4199" → "+1-253-234-4199" */
    public static function e164(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone) ?? '';
        $digits = strlen($digits) === 11 && $digits[0] === '1' ? substr($digits, 1) : $digits;

        return strlen($digits) === 10 ? '+1-'.substr($digits, 0, 3).'-'.substr($digits, 3, 3).'-'.substr($digits, 6) : $phone;
    }
}
