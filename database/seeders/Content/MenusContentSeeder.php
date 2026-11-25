<?php

declare(strict_types=1);

namespace Database\Seeders\Content;

use Illuminate\Database\Seeder;
use Uptms\Media\Models\Media;
use Uptms\Menus\Models\Menu;
use Uptms\Menus\Services\MenuTree;
use Uptms\Pages\Models\Page;

/**
 * The five menus of Figma 17, linked to the seeded pages. A menu that
 * already exists is left as staff saved it, unless it is still empty and
 * the seed now has items for it (the footer's legal menu gained the
 * privacy policy after the first installs).
 */
class MenusContentSeeder extends Seeder
{
    public function __construct(private readonly MenuTree $tree) {}

    public function run(): void
    {
        $id = fn (string $path): int => (int) Page::query()->where('path', $path)->value('id');
        $page = fn (string $label, string $path): array => ['label' => $label, 'type' => 'page', 'page_id' => $id($path)];
        $anchor = fn (string $label, string $path, string $anchor): array => ['label' => $label, 'type' => 'anchor', 'page_id' => $id($path), 'anchor' => $anchor];

        $menus = [
            'header' => ['Header', 'Pills in the desktop header.', [
                $page('Home', '/'),
                [...$page('TMS', '/tms'), 'children' => [$anchor('How TMS works', '/tms', 'basics')]],
                $page('Contact', '/contact'),
            ]],
            'mobile' => ['Mobile menu', 'Tablet sheet and full-screen phone menu.', [
                $page('Home', '/'),
                $page('TMS', '/tms'),
                $page('Contact', '/contact'),
                $anchor('Care team', '/', 'care-team'),
                $anchor('Insurance', '/', 'insurance'),
                $anchor('Location & directions', '/contact', 'location'),
            ]],
            'section-nav' => ['Section navigation', 'Anchor pills under the home hero.', [
                $anchor('Our care team', '/', 'care-team'),
                $anchor('Insurance & partners', '/', 'insurance'),
                $anchor('Patient voices', '/', 'patient-voices'),
                $anchor('Your questions', '/', 'faq'),
                $anchor('Find our clinic', '/', 'location'),
            ]],
            'footer-explore' => ['Footer · Explore', 'Links in the footer’s Explore column.', [
                $page('Home', '/'),
                $page('TMS', '/tms'),
                $page('Contact', '/contact'),
                $anchor('Care team', '/', 'care-team'),
                $anchor('Insurance', '/', 'insurance'),
                $anchor('Location & directions', '/', 'location'),
            ]],
            'footer-legal' => ['Footer · Legal', 'Small links in the footer’s bottom bar (privacy, accessibility).', [
                $page('Privacy policy', '/privacy'),
            ]],
            // Filled only once the PDFs are uploaded (SiteMediaSeeder::DOCUMENTS); an empty menu is refilled on the next seed.
            'documents' => ['Official documents', 'The SPRAVATO® documents in the footer and the FAQ card.', $this->documents()],
        ];

        $order = 0;

        foreach ($menus as $key => [$name, $description, $items]) {
            $order++;
            $menu = Menu::query()->where('key', $key)->first();

            if ($menu === null) {
                $menu = Menu::query()->create(['key' => $key, 'name' => $name, 'description' => $description, 'sort_order' => $order]);
            } elseif ($menu->items()->exists()) {
                continue;
            }

            $this->tree->save($menu, $items);
        }
    }

    /** @return list<array{label: string, type: string, url: string}> */
    private function documents(): array
    {
        $items = [];

        foreach (SiteMediaSeeder::DOCUMENTS as $file => [, $title]) {
            $media = Media::query()->where('original_name', $file)->first();
            $path = $media instanceof Media ? parse_url($media->url(), PHP_URL_PATH) : null;

            if (is_string($path)) {
                $items[] = ['label' => $title, 'type' => 'link', 'url' => $path];
            }
        }

        return $items;
    }
}
