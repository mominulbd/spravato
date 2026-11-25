<?php

declare(strict_types=1);

use Database\Seeders\SiteContentSeeder;
use Illuminate\Support\Facades\Storage;
use Uptms\Menus\Models\Menu;
use Uptms\Menus\Services\MenuTree;
use Uptms\Pages\Models\Page;

it('seeds the three designed pages and the privacy policy, published, with numbered sections and resolved collections', function () {
    Storage::fake('public');
    $this->seed(SiteContentSeeder::class);
    $this->seed(SiteContentSeeder::class); // idempotent

    expect(Page::query()->count())->toBe(4);

    $home = $this->getJson('/api/v1/public/page?path=%2F')->assertOk()->json('data');
    $types = array_column($home['blocks'], 'type');

    expect($types)->toBe([
        'landing_hero', 'media_text', 'criteria', 'visit_steps', 'schedule', 'video_pair', 'care_team', 'coverage_location', 'faq', 'cta_band',
    ])
        ->and(array_values(array_filter(array_column($home['blocks'], 'number'))))->toBe(['01', '02', '03', '04', '05', '06', '07', '08'])
        ->and($home['blocks'][8]['sources']['faqs'])->toHaveCount(7)
        ->and(array_column($home['blocks'][6]['sources']['team'], 'name'))->toEqualCanonicalizing(['Ayub Mwaura', 'Joy Kiongo'])
        ->and($home['blocks'][0]['data']['image']['alt'])->not->toBe('')
        ->and($home['blocks'][5]['data']['videos'][0]['poster']['alt'])->not->toBe('')
        ->and($home['seo']['title'])->toContain('Spravato');

    foreach (['/tms' => 8, '/contact' => 3, '/privacy' => 1] as $path => $count) {
        $this->getJson('/api/v1/public/page?path='.$path)->assertOk()->assertJsonCount($count, 'data.blocks');
    }

    $this->getJson('/api/v1/public/page?path=/privacy')
        ->assertJsonPath('data.template', 'legal')
        ->assertJsonPath('data.blocks.0.type', 'rich_text')
        ->assertJsonPath('data.blocks.0.data.headline', 'Privacy policy');

    $this->getJson('/api/v1/public/menus')
        ->assertJsonCount(5, 'data.section-nav')
        ->assertJsonCount(0, 'data.documents')
        ->assertJsonPath('data.footer-legal.0.href', '/privacy');

    // An install seeded before the privacy policy existed has an empty legal menu: it is filled, edited menus are not.
    $menus = app(MenuTree::class);
    $menus->save(Menu::query()->where('key', 'footer-legal')->firstOrFail(), []);
    $menus->save(Menu::query()->where('key', 'header')->firstOrFail(), [['label' => 'Only home', 'type' => 'page', 'page_id' => Page::query()->where('path', '/')->value('id')]]);
    $this->seed(SiteContentSeeder::class);

    $this->getJson('/api/v1/public/menus')
        ->assertJsonPath('data.footer-legal.0.href', '/privacy')
        ->assertJsonCount(1, 'data.header')
        ->assertJsonPath('data.header.0.label', 'Only home');
});

it('links the official SPRAVATO® documents once their PDFs are supplied', function () {
    Storage::fake('public');
    $file = database_path('seeders/assets/spravato/spravato-medication-guide.pdf');
    // The smallest file the media pipeline accepts as a PDF.
    file_put_contents($file, "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Kids[]/Count 0>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n");

    try {
        $this->seed(SiteContentSeeder::class);
    } finally {
        unlink($file);
    }

    $documents = $this->getJson('/api/v1/public/menus')->assertOk()->json('data.documents');
    expect(array_column($documents, 'label'))->toBe(['Medication Guide'])
        ->and($documents[0]['href'])->toStartWith('/storage/');

    $blocks = collect($this->getJson('/api/v1/public/page?path=%2F')->json('data.blocks'));
    expect($blocks->firstWhere('type', 'faq')['data']['aside_links'])->toEqual([['label' => 'Medication Guide →', 'href' => $documents[0]['href']]])
        ->and($blocks->firstWhere('type', 'media_text')['data']['link']['href'])->toBe($documents[0]['href']);
});
