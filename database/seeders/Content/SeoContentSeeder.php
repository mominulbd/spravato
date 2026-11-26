<?php

declare(strict_types=1);

namespace Database\Seeders\Content;

use Illuminate\Database\Seeder;
use Uptms\Pages\Models\Page;
use Uptms\Seo\Models\SeoMeta;
use Uptms\Seo\Services\SeoSources;
use Uptms\Seo\Services\SeoWorkspace;

/**
 * Search titles, descriptions and keyphrases for the seeded pages. The
 * Spravato page at / is the only page the website renders (DEVELOPMENT-PLAN.md,
 * Phase 3): its share image is the hero photo and its schema adds the
 * SPRAVATO® Drug node. The UPTMS TMS, Contact and Privacy pages stay out of
 * search and the sitemap until the website shows them.
 * Fills only pages without SEO yet, then scores them.
 */
class SeoContentSeeder extends Seeder
{
    public function __construct(private readonly SeoWorkspace $workspace, private readonly SeoSources $sources) {}

    public function run(): void
    {
        foreach ($this->entries() as $path => $values) {
            $page = Page::query()->where('path', $path)->first();

            if (! $page instanceof Page || SeoMeta::query()->where('seoable_type', 'page')->where('seoable_id', $page->id)->exists()) {
                continue;
            }

            $meta = SeoMeta::blank('page', $page->id);
            $meta->fill($values)->save();

            $subject = $this->sources->get('page')?->subject($page->id, live: true);
            if ($subject !== null) {
                $this->workspace->rescore($subject);
            }
        }
    }

    /** @return array<string, array<string, mixed>> */
    private function entries(): array
    {
        $nimh = ['label' => 'National Institute of Mental Health — Depression', 'url' => 'https://www.nimh.nih.gov/health/topics/depression'];
        $fda = ['label' => 'FDA — BrainsWay Deep TMS for OCD (510(k))', 'url' => 'https://www.accessdata.fda.gov/cdrh_docs/pdf17/DEN170078.pdf'];
        $hidden = ['robots_index' => false, 'in_sitemap' => false];
        $hero = SiteMediaSeeder::id('spravato-nasal-spray-use.jpg');

        return [
            '/' => [
                'meta_title' => 'Spravato® Treatment near Tacoma & University Place %sep% UPTMS',
                'meta_description' => 'Explore Spravato® (esketamine) treatment in University Place near Tacoma. Learn about eligibility, supervised visits, insurance and consultation options at UPTMS.',
                'focus_keyphrase' => 'spravato treatment tacoma',
                'keyphrases' => ['esketamine nasal spray', 'treatment-resistant depression', 'spravato university place'],
                'breadcrumb_title' => 'Home',
                'citations' => [$nimh],
                'og_image_id' => $hero !== 0 ? $hero : null,
                'schema_type' => 'MedicalWebPage',
                'schema_about' => 'Treatment-resistant depression',
                // Facts from the SPRAVATO® label only; no claims about outcomes.
                'schema_custom' => [[
                    '@type' => 'Drug',
                    'name' => 'SPRAVATO®',
                    'proprietaryName' => 'Spravato',
                    'nonProprietaryName' => 'esketamine',
                    'activeIngredient' => 'esketamine',
                    'dosageForm' => 'Nasal spray',
                    'administrationRoute' => 'Nasal',
                    'prescriptionStatus' => 'https://schema.org/PrescriptionOnly',
                    'isProprietary' => true,
                    'isAvailableGenerically' => false,
                ]],
            ],
            '/tms' => [
                'meta_title' => 'Deep TMS for Depression & OCD %sep% %site%',
                'meta_description' => 'How Deep TMS works, who it may help, what a course of treatment involves and how insurance usually covers it — from a psychiatric team in University Place, WA.',
                'focus_keyphrase' => 'deep tms',
                'keyphrases' => ['brainsway h7 coil', 'tms for ocd'],
                'citations' => [$nimh, $fda],
                ...$hidden,
            ],
            '/contact' => [
                'meta_title' => 'Contact University Place TMS Clinic %sep% Free Phone Consultation',
                'meta_description' => 'Call or request a free phone consultation with University Place TMS Clinic. Office hours Mon–Fri, 8 AM–5 PM, at 4007 Bridgeport Way W, Suite B.',
                'focus_keyphrase' => 'tms clinic university place',
                ...$hidden,
            ],
            '/privacy' => [
                'meta_title' => 'Privacy Policy %sep% %site%',
                'meta_description' => 'What the University Place TMS Clinic website collects when you request a consultation or take the self-check, how it is protected and kept, and the choices you have.',
                'focus_keyphrase' => 'privacy policy',
                'breadcrumb_title' => 'Privacy',
                ...$hidden,
            ],
        ];
    }
}
