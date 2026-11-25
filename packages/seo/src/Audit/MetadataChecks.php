<?php

declare(strict_types=1);

namespace Uptms\Seo\Audit;

use Uptms\Seo\Domain\Enums\IssueSeverity;
use Uptms\Seo\Domain\SeoIssue;
use Uptms\Seo\Domain\SeoSubject;
use Uptms\Seo\Models\SeoMeta;
use Uptms\Seo\Services\RobotsTxt;
use Uptms\Seo\Services\SeoAnalyzer;
use Uptms\Seo\Services\SeoSettings;

/**
 * The audit's own checks over every indexable URL: titles, descriptions,
 * keyphrases, alt text, internal links and robots.txt. Per-page results
 * come from the same SeoAnalyzer the editors use, so a page's checklist
 * and the overview never disagree.
 */
final readonly class MetadataChecks
{
    public function __construct(private RobotsTxt $robots, private SeoSettings $settings) {}

    /**
     * @param  list<array{subject: SeoSubject, meta: SeoMeta, title: string, description: string, analysis: array{score: int, seo: list<array{key: string, status: string, text: string}>}}>  $pages  indexable pages only
     * @return list<SeoIssue>
     */
    public function issues(array $pages): array
    {
        $checked = count($pages).' '.(count($pages) === 1 ? 'URL' : 'URLs');
        $item = fn (SeoSubject $s): array => ['key' => $s->key(), 'label' => $s->title, 'path' => $s->path, 'admin_path' => $s->adminPath.'?tab=seo'];
        $where = fn (callable $test): array => array_values(array_map(fn (array $p): array => $item($p['subject']), array_filter($pages, $test)));
        $failed = fn (array $p, string $key, string $status): bool => array_filter($p['analysis']['seo'], fn (array $c): bool => $c['key'] === $key && $c['status'] === $status) !== [];
        $issues = [];

        $blocked = $where(fn (array $p): bool => ! $this->robots->test((string) $this->settings->get('robots_txt'), $p['subject']->path)['allowed']);
        $issues[] = $blocked !== []
            ? new SeoIssue('robots_blocked', IssueSeverity::Urgent, count($blocked) === 1 ? 'robots.txt blocks a published page' : 'robots.txt blocks published pages', SeoIssue::names($blocked), $blocked, ['label' => 'Fix', 'to' => '/seo/sitemap'])
            : SeoIssue::passed('robots_blocked', 'robots.txt lets Google read every page', "Checked {$checked}");

        $missing = $where(fn (array $p): bool => trim($p['description']) === '');
        $issues[] = $missing !== []
            ? new SeoIssue('description_missing', IssueSeverity::Urgent, 'Missing meta description', SeoIssue::names($missing), $missing)
            : SeoIssue::passed('description_missing', 'Every page has a meta description', "Checked {$checked}");

        $long = $where(fn (array $p): bool => SeoAnalyzer::pixels($p['title'], 'title') > SeoAnalyzer::TITLE_MAX_PX);
        $issues[] = $long !== []
            ? new SeoIssue('title_long', IssueSeverity::Warning, 'SEO title longer than 60 characters', SeoIssue::names($long), $long)
            : SeoIssue::passed('title_long', 'SEO titles fit in search results', "Checked {$checked}");

        $issues[] = $this->duplicates($pages, $item, $checked);
        $issues[] = $this->images($pages);

        $competing = $where(fn (array $p): bool => $failed($p, 'keyphrase_unique', 'problem'));
        if ($competing !== []) {
            $issues[] = new SeoIssue('keyphrase_competing', IssueSeverity::Warning, 'Pages compete for the same keyphrase', SeoIssue::names($competing), $competing);
        }

        $cut = $where(fn (array $p): bool => trim($p['description']) !== '' && SeoAnalyzer::pixels($p['description'], 'description') > SeoAnalyzer::DESCRIPTION_MAX_PX);
        if ($cut !== []) {
            $issues[] = new SeoIssue('description_long', IssueSeverity::Notice, 'Meta description gets cut off', SeoIssue::names($cut), $cut);
        }

        $orphans = $where(fn (array $p): bool => $failed($p, 'incoming_links', 'improve'));
        if ($orphans !== []) {
            $issues[] = new SeoIssue('no_incoming_links', IssueSeverity::Notice, count($orphans) === 1 ? 'Page has no internal links pointing to it' : 'Pages have no internal links pointing to them', SeoIssue::names($orphans), $orphans, count($orphans) === 1 ? ['label' => 'View', 'to' => $orphans[0]['admin_path']] : null);
        }

        $noKeyphrase = $where(fn (array $p): bool => trim((string) $p['meta']->focus_keyphrase) === '');
        if ($noKeyphrase !== []) {
            $issues[] = new SeoIssue('keyphrase_missing', IssueSeverity::Notice, 'No focus keyphrase set', SeoIssue::names($noKeyphrase), $noKeyphrase);
        }

        return $issues;
    }

    /**
     * @param  list<array{subject: SeoSubject, meta: SeoMeta, title: string, description: string, analysis: array{score: int, seo: list<array{key: string, status: string, text: string}>}}>  $pages
     * @param  callable(SeoSubject): array{key: string, label: string, path: string|null, admin_path: string|null}  $item
     */
    private function duplicates(array $pages, callable $item, string $checked): SeoIssue
    {
        $groups = [];

        foreach ($pages as $page) {
            foreach (['title' => $page['title'], 'description' => $page['description']] as $field => $value) {
                $value = mb_strtolower(trim($value));

                if ($value !== '') {
                    $groups[$field.':'.$value][] = $page['subject'];
                }
            }
        }

        $items = [];

        foreach ($groups as $subjects) {
            if (count($subjects) > 1) {
                foreach ($subjects as $subject) {
                    $items[$subject->key()] = $item($subject);
                }
            }
        }

        return $items !== []
            ? new SeoIssue('duplicates', IssueSeverity::Warning, 'Duplicate titles or descriptions', SeoIssue::names(array_values($items)), array_values($items))
            : SeoIssue::passed('duplicates', 'No duplicate titles or descriptions', "Checked {$checked}");
    }

    /** @param list<array{subject: SeoSubject, meta: SeoMeta, title: string, description: string, analysis: array{score: int, seo: list<array{key: string, status: string, text: string}>}}> $pages */
    private function images(array $pages): SeoIssue
    {
        $images = [];
        $onPages = [];

        foreach ($pages as $page) {
            foreach ($page['subject']->images as $image) {
                if (trim($image['alt']) !== '') {
                    continue;
                }

                $key = $image['id'] !== null ? 'image:'.$image['id'] : 'file:'.$image['filename'];
                $images[$key] ??= ['key' => $key, 'label' => $image['filename'] !== '' ? $image['filename'] : 'Image', 'path' => $page['subject']->path, 'admin_path' => $image['id'] !== null ? '/media?file='.$image['id'] : $page['subject']->adminPath];
                $onPages[$page['subject']->key()] = true;
            }
        }

        if ($images === []) {
            return SeoIssue::passed('image_alt', 'Every image has alt text', 'Checked the images on every published page');
        }

        $count = count($images);
        $pagesCount = count($onPages);

        return new SeoIssue(
            'image_alt',
            IssueSeverity::Warning,
            'Images without alt text',
            ($count === 1 ? '1 image' : "{$count} images").' on '.($pagesCount === 1 ? '1 page' : "{$pagesCount} pages"),
            array_values($images),
            ['label' => 'Fix', 'to' => '/media?show=no_alt'],
        );
    }
}
