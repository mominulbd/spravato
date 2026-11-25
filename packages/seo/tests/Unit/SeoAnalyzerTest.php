<?php

declare(strict_types=1);

use Uptms\Seo\Domain\SeoSubject;
use Uptms\Seo\Services\SeoAnalyzer;

function subject(array $overrides = []): SeoSubject
{
    return new SeoSubject(...[
        'type' => 'page', 'id' => 1, 'label' => 'TMS', 'path' => '/deep-tms-therapy', 'adminPath' => '/pages/1', 'published' => true,
        'title' => 'Deep TMS therapy', 'templateKey' => 'page',
        'bodyText' => str_repeat('Deep TMS therapy helps some people when depression does not lift. ', 40),
        'firstParagraph' => 'Deep TMS therapy helps some people when depression does not lift.',
        'headings' => ['How deep TMS therapy works'],
        'images' => [['id' => 1, 'alt' => 'Clinician with a patient', 'filename' => 'deep-tms-therapy.jpg']],
        'links' => ['/contact', 'https://www.nimh.nih.gov'],
        ...$overrides,
    ]);
}

it('passes a well-optimised page', function () {
    $result = (new SeoAnalyzer)->analyze(subject(), [
        'title' => 'Deep TMS Therapy in University Place, WA | UPTMS',
        'description' => 'Deep TMS therapy when depression won’t lift: what to expect, how it works, and how to ask about insurance. Your first phone consultation is free.',
        'keyphrase' => 'deep tms therapy', 'related' => [], 'index' => true,
    ], ['focus_elsewhere' => [], 'incoming_links' => 2]);

    $statuses = array_column($result['seo'], 'status', 'key');
    expect($statuses['keyphrase_title'])->toBe('good')
        ->and($statuses['keyphrase_slug'])->toBe('good')
        ->and($statuses['keyphrase_heading'])->toBe('good')
        ->and($statuses['keyphrase_image'])->toBe('good')
        ->and($statuses['description_length'])->toBe('good')
        ->and($result['score'])->toBeGreaterThanOrEqual(85);
});

it('reports what is missing, problems first', function () {
    $result = (new SeoAnalyzer)->analyze(subject(['images' => [['id' => 1, 'alt' => '', 'filename' => 'x.jpg']], 'links' => []]), [
        'title' => '', 'description' => '', 'keyphrase' => null, 'related' => [], 'index' => false,
    ]);

    expect($result['seo'][0]['status'])->toBe('problem')
        ->and(array_column($result['seo'], 'key'))->toContain('keyphrase', 'image_alt', 'noindex')
        ->and($result['score'])->toBeLessThan(50);
});

it('estimates rendered widths like Google’s results', function () {
    expect(SeoAnalyzer::pixels('Deep TMS Therapy in University Place, WA | UPTMS', 'title'))->toBeLessThan(580)
        ->and(SeoAnalyzer::pixels(str_repeat('Deep TMS Therapy ', 6), 'title'))->toBeGreaterThan(580)
        ->and(SeoAnalyzer::syllables('therapy'))->toBe(3);
});
