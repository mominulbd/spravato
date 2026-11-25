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
        'hero', 'section_nav', 'copy_panel', 'media_text',
        'team', 'cta_band', 'insurance', 'value_grid', 'steps', 'testimonials', 'faq', 'location', 'cta_band',
    ])
        ->and(array_values(array_filter(array_column($home['blocks'], 'number'))))->toBe(['01', '02', '03', '04', '05', '06', '07', '08', '09'])
        ->and($home['blocks'][10]['sources']['faqs'])->toHaveCount(16)
        ->and($home['blocks'][6]['sources']['partners'])->toHaveCount(10)
        ->and($home['blocks'][0]['data']['image']['alt'])->not->toBe('');

    foreach (['/tms' => 8, '/contact' => 3, '/privacy' => 1] as $path => $count) {
        $this->getJson('/api/v1/public/page?path='.$path)->assertOk()->assertJsonCount($count, 'data.blocks');
    }

    $this->getJson('/api/v1/public/page?path=/privacy')
        ->assertJsonPath('data.template', 'legal')
        ->assertJsonPath('data.blocks.0.type', 'rich_text')
        ->assertJsonPath('data.blocks.0.data.headline', 'Privacy policy');

    $this->getJson('/api/v1/public/menus')
        ->assertJsonCount(5, 'data.section-nav')
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
