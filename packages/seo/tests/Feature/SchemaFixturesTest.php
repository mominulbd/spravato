<?php

declare(strict_types=1);

use Uptms\Collections\Models\Faq;
use Uptms\Collections\Models\TeamMember;
use Uptms\Pages\Actions\SavePageContentAction;
use Uptms\Pages\Models\Page;
use Uptms\Publishing\Domain\Enums\PublishStatus;
use Uptms\Publishing\Services\PublicCache;

/*
 * Schema fixtures (DEVELOPMENT-PLAN.md, Phase 3): the real seeded content,
 * fetched the way the website fetches it, and the JSON-LD graph it yields.
 */

beforeEach(function () {
    seedSiteContent();
    $this->home = Page::query()->where('path', '/')->firstOrFail();

    // Unpublished content that must never reach the graph.
    $faq = new Faq(['group' => 'spravato', 'question' => 'Draft question?', 'answer' => 'Draft answer.', 'in_schema' => true, 'sort_order' => 99]);
    $faq->is_published = false;
    $faq->save();
    $member = new TeamMember(['name' => 'Draft Clinician', 'role_title' => 'Not yet announced', 'show_on' => ['home'], 'schema_type' => 'Person', 'sort_order' => 99]);
    $member->is_published = false;
    $member->save();

    // A working-copy edit that is saved but not published.
    $blocks = $this->home->blocks()->orderBy('sort_order')->get()
        ->map(fn ($block): array => ['type' => $block->type, 'is_visible' => true, 'data' => $block->type === 'landing_hero' ? [...$block->data, 'headline' => 'Unpublished *headline*'] : $block->data])
        ->all();
    app(SavePageContentAction::class)->execute($this->home, $blocks, $this->home->lock_version, null);

    app(PublicCache::class)->flush();
});

/** @return array<string, mixed> the first node whose @type is or includes $type */
function schemaNode(array $graph, string $type): array
{
    return collect($graph)->first(fn (array $node): bool => in_array($type, (array) $node['@type'], true)) ?? [];
}

it('describes the Spravato page to search engines with the clinic, page, FAQs and care team', function () {
    $page = $this->getJson('/api/v1/public/page?path=%2F')->assertOk()->json('data');
    $seo = $page['seo'];
    $graph = $seo['json_ld'];
    $blocks = collect($page['blocks'])->keyBy('type');
    $hero = $blocks['landing_hero']['data']['image'];

    expect($seo['title'])->toBe('Spravato® Treatment near Tacoma & University Place | UPTMS')
        ->and($seo['description'])->toContain('University Place near Tacoma')
        ->and($seo['canonical'])->toEndWith('/')
        ->and($seo['robots']['index'])->toBeTrue()
        ->and($seo['open_graph']['image']['url'])->toBe($hero['url'])
        ->and($seo['open_graph']['image']['alt'])->not->toBe('');

    // One node of each kind, and nothing left over from the TMS site.
    $types = array_merge(...array_map(fn (array $node): array => (array) $node['@type'], $graph));
    expect($types)->toContain('MedicalClinic', 'WebSite', 'MedicalWebPage', 'BreadcrumbList', 'FAQPage', 'Person', 'Drug')
        ->and($types)->not->toContain('MedicalTherapy', 'Article')
        ->and(json_encode($graph))->not->toContain('BrainsWay')->not->toContain('Deep TMS');

    $clinic = schemaNode($graph, 'MedicalClinic');
    expect($clinic['name'])->toStartWith('University Place TMS Clinic')
        ->and($clinic['telephone'])->toBe('+1-253-234-4199')
        ->and($clinic['address'])->toMatchArray(['streetAddress' => '4007 Bridgeport Way W, Suite B', 'addressLocality' => 'University Place', 'addressRegion' => 'WA', 'postalCode' => '98466'])
        ->and($clinic['openingHoursSpecification'])->not->toBeEmpty();

    $webpage = schemaNode($graph, 'MedicalWebPage');
    expect($webpage['about'])->toBe(['@type' => 'MedicalCondition', 'name' => 'Treatment-resistant depression'])
        ->and($webpage['primaryImageOfPage']['url'])->toBe($hero['url'])
        ->and($webpage['isPartOf']['@id'])->toBe(schemaNode($graph, 'WebSite')['@id'])
        ->and(schemaNode($graph, 'BreadcrumbList')['itemListElement'])->toHaveCount(1);

    // The FAQ block and the FAQPage list the same published questions.
    $faq = schemaNode($graph, 'FAQPage');
    expect(array_column($faq['mainEntity'], 'name'))->toBe(array_column($blocks['faq']['sources']['faqs'], 'question'))
        ->and($faq['mainEntity'])->toHaveCount(7)
        ->and($faq['mainEntity'][0]['acceptedAnswer']['text'])->toBe($blocks['faq']['sources']['faqs'][0]['answer']);

    // The care team block's people, each working for the clinic.
    $people = collect($graph)->filter(fn (array $node): bool => in_array('Person', (array) $node['@type'], true));
    expect($people->pluck('name')->all())->toEqualCanonicalizing(array_column($blocks['care_team']['sources']['team'], 'name'))
        ->and($people->pluck('name')->all())->toEqualCanonicalizing(['Ayub Mwaura', 'Joy Kiongo'])
        ->and($people->every(fn (array $p): bool => $p['worksFor'] === ['@id' => $clinic['@id']]))->toBeTrue();
    $ayub = $people->firstWhere('name', 'Ayub Mwaura');
    expect($ayub['honorificSuffix'])->toBe('MSN, PMHNP-BC')
        ->and($ayub['jobTitle'])->toContain('Psychiatric nurse practitioner')
        ->and($ayub['image'])->toBeString()
        // A psychiatric nurse practitioner must never be marked up as a physician.
        ->and($ayub['@type'])->toBe('Person');

    expect(schemaNode($graph, 'Drug'))->toMatchArray(['name' => 'SPRAVATO®', 'nonProprietaryName' => 'esketamine', 'administrationRoute' => 'Nasal']);

    // Drafts stay out: the unpublished FAQ, team member and headline.
    expect(json_encode($page))->not->toContain('Draft question')->not->toContain('Draft Clinician')->not->toContain('Unpublished');
});

it('lists only the Spravato page in the sitemap and keeps the unrendered pages out of search', function () {
    $entries = $this->getJson('/api/v1/public/sitemap')->assertOk()->json('data');

    expect(array_column($entries, 'path'))->toBe(['/'])
        ->and(array_column($entries[0]['images'], 'url'))->toContain($this->getJson('/api/v1/public/page?path=%2F')->json('data.blocks.0.data.image.url'));

    foreach (['/tms', '/contact', '/privacy'] as $path) {
        expect($this->getJson('/api/v1/public/page?path='.$path)->assertOk()->json('data.seo.robots.index'))->toBeFalse();
    }
});

it('serves no page and no SEO for an address that is not published', function () {
    $draft = new Page(['title' => 'Coming soon']);
    $draft->forceFill(['slug' => 'coming-soon', 'path' => '/coming-soon', 'template' => 'standard', 'status' => PublishStatus::Draft, 'is_home' => false, 'lock_version' => 1])->save();
    $this->home->forceFill(['status' => PublishStatus::Draft])->save();
    app(PublicCache::class)->flush();

    foreach (['%2F', '/coming-soon', '/does-not-exist'] as $path) {
        $response = $this->getJson('/api/v1/public/page?path='.$path)->assertNotFound()->assertJsonPath('error_code', 'NOT_FOUND');
        expect($response->json('data'))->toBeNull()
            ->and($response->getContent())->not->toContain('json_ld')->not->toContain('MedicalClinic');
    }

    expect(array_column($this->getJson('/api/v1/public/sitemap')->json('data'), 'path'))->not->toContain('/', '/coming-soon');
});
