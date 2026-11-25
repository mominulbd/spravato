<?php

declare(strict_types=1);

use Uptms\Menus\Models\Menu;
use Uptms\Pages\Models\Page;

function publishedPage(string $slug): Page
{
    $id = asAdmin(staffUser('manager'))->postJson('/api/v1/admin/pages', ['title' => ucfirst($slug), 'slug' => $slug, 'template' => 'legal'])->json('data.id');
    asAdmin(staffUser('manager'))->putJson("/api/v1/admin/pages/{$id}/content", ['lock_version' => 1, 'blocks' => [['type' => 'rich_text', 'data' => ['body' => 'Text']]]])->assertOk();
    asAdmin(staffUser('manager'))->postJson("/api/v1/admin/pages/{$id}/publish")->assertOk();

    return Page::query()->findOrFail($id);
}

beforeEach(function () {
    Menu::query()->create(['key' => 'header', 'name' => 'Header', 'sort_order' => 1]);
    Menu::query()->create(['key' => 'section-nav', 'name' => 'Section navigation', 'sort_order' => 2]);
});

it('saves a nested menu whose page links follow the page address', function () {
    $tms = publishedPage('tms');
    $draft = asAdmin(staffUser('manager'))->postJson('/api/v1/admin/pages', ['title' => 'Draft', 'slug' => 'draft', 'template' => 'legal'])->json('data.id');

    asAdmin(staffUser('manager'))->putJson('/api/v1/admin/menus/header', ['items' => [
        ['label' => 'TMS', 'type' => 'page', 'page_id' => $tms->id, 'children' => [
            ['label' => 'How TMS works', 'type' => 'anchor', 'page_id' => $tms->id, 'anchor' => 'basics'],
        ]],
        ['label' => 'Not live', 'type' => 'page', 'page_id' => $draft],
        ['label' => 'Hidden', 'type' => 'link', 'url' => '/x', 'is_visible' => false],
        ['label' => 'Call', 'type' => 'link', 'url' => 'tel:+12532344199'],
    ]])->assertOk()->assertJsonCount(4, 'data.items')->assertJsonCount(2, 'data.preview');

    asAdmin(staffUser('manager'))->putJson("/api/v1/admin/pages/{$tms->id}", ['title' => 'TMS', 'slug' => 'tms-therapy', 'template' => 'legal'])->assertOk();

    $this->getJson('/api/v1/public/menus')->assertOk()
        ->assertJsonPath('data.header.0.href', '/tms-therapy')
        ->assertJsonPath('data.header.0.children.0.href', '/tms-therapy#basics')
        ->assertJsonPath('data.header.1.href', 'tel:+12532344199')
        ->assertJsonPath('data.section-nav', []);
});

it('replaces the whole tree on every save, nested items included', function () {
    $tms = publishedPage('tms');
    $items = ['items' => [
        ['label' => 'Home', 'type' => 'link', 'url' => '/'],
        ['label' => 'TMS', 'type' => 'page', 'page_id' => $tms->id, 'children' => [
            ['label' => 'How TMS works', 'type' => 'anchor', 'page_id' => $tms->id, 'anchor' => 'basics'],
            ['label' => 'Areas of care', 'type' => 'anchor', 'page_id' => $tms->id, 'anchor' => 'areas'],
        ]],
        ['label' => 'Contact', 'type' => 'link', 'url' => '/contact'],
    ]];

    asAdmin(staffUser('manager'))->putJson('/api/v1/admin/menus/header', $items)->assertOk();
    asAdmin(staffUser('manager'))->putJson('/api/v1/admin/menus/header', $items)->assertOk()->assertJsonCount(3, 'data.items');

    expect(Menu::query()->where('key', 'header')->firstOrFail()->items()->count())->toBe(5);
});

it('validates items and keeps saving to content.publish', function () {
    assertErrorCode(asAdmin(staffUser('staff'))->putJson('/api/v1/admin/menus/header', ['items' => []]), 'FORBIDDEN', 403);
    asAdmin(staffUser('staff'))->getJson('/api/v1/admin/menus')->assertOk()->assertJsonPath('data.0.key', 'header');

    asAdmin(staffUser('manager'))->putJson('/api/v1/admin/menus/header', ['items' => [
        ['label' => 'Bad', 'type' => 'link', 'url' => 'javascript:alert(1)'],
        ['label' => 'Ghost', 'type' => 'page', 'page_id' => 999_999],
    ]])->assertStatus(422)->assertJsonStructure(['errors' => ['items.0.url']]);

    $errors = asAdmin(staffUser('manager'))->putJson('/api/v1/admin/menus/header', ['items' => [
        ['label' => 'Ghost', 'type' => 'page', 'page_id' => 999_999],
    ]])->assertStatus(422)->json('errors');
    expect($errors['items.0.page_id'][0])->toBe('“Ghost” points at a page that no longer exists. Choose another page for it, or remove it.');
});

it('names the item whose page was deleted and shows it without an address in the editor', function () {
    $privacy = asAdmin(staffUser('manager'))->postJson('/api/v1/admin/pages', ['title' => 'Privacy', 'slug' => 'privacy', 'template' => 'legal'])->json('data.id');
    asAdmin(staffUser('manager'))->putJson('/api/v1/admin/menus/header', ['items' => [['label' => 'Privacy policy', 'type' => 'page', 'page_id' => $privacy]]])->assertOk();

    asAdmin(staffUser('manager'))->deleteJson("/api/v1/admin/pages/{$privacy}")->assertOk();

    asAdmin(staffUser('manager'))->getJson('/api/v1/admin/menus/header')->assertOk()
        ->assertJsonPath('data.items.0.page_id', $privacy)
        ->assertJsonPath('data.items.0.page_title', null)
        ->assertJsonPath('data.items.0.href', null)
        ->assertJsonCount(0, 'data.preview');

    $errors = asAdmin(staffUser('manager'))->putJson('/api/v1/admin/menus/header', ['items' => [['label' => 'Privacy policy', 'type' => 'page', 'page_id' => $privacy]]])
        ->assertStatus(422)->json('errors');
    expect($errors['items.0.page_id'][0])->toBe('“Privacy policy” points at a page that no longer exists. Choose another page for it, or remove it.');
});
