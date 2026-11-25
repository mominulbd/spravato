<?php

declare(strict_types=1);

namespace Uptms\Seo\Services;

use Illuminate\Support\Str;
use Uptms\Seo\Domain\SeoSubject;

/**
 * The SEO and readability checklist of Figma 08 and 12. One implementation:
 * the admin calls it live while someone types, the editor saves its result
 * and the nightly audit runs it over the whole site.
 *
 * Each check is good (full weight), improve (half) or problem (none); the
 * score is the weighted share.
 */
final class SeoAnalyzer
{
    /** Google truncates titles near 580 px and descriptions near 920 px on desktop. */
    public const int TITLE_MAX_PX = 580;

    public const int DESCRIPTION_MAX_PX = 920;

    /**
     * @param  array{title: string, description: string, keyphrase: string|null, related: list<string>, index: bool}  $meta  resolved values (templates applied)
     * @param  array{focus_elsewhere: list<string>, incoming_links: int|null}  $site  what the rest of the site says
     * @return array{score: int, readability: int, seo: list<array{key: string, status: string, text: string}>, readability_checks: list<array{key: string, status: string, text: string}>, title: array{chars: int, px: int, max_px: int}, description: array{chars: int, px: int, max_px: int}, keyphrase_uses: int}
     */
    public function analyze(SeoSubject $subject, array $meta, array $site = ['focus_elsewhere' => [], 'incoming_links' => null]): array
    {
        $keyphrase = $meta['keyphrase'] !== null ? trim(mb_strtolower($meta['keyphrase'])) : '';
        $text = $subject->bodyText;
        $lower = mb_strtolower($text);
        $uses = $keyphrase === '' ? 0 : $this->countPhrase($lower, $keyphrase);
        $words = max(1, str_word_count($text));
        $titlePx = self::pixels($meta['title'], 'title');
        $descriptionPx = self::pixels($meta['description'], 'description');
        $checks = [];

        // Focus keyphrase.
        if ($keyphrase === '') {
            $checks[] = $this->check('keyphrase', 'problem', 'Set a focus keyphrase so the checklist can test this page against what people search for.');
        } else {
            $checks[] = $this->contains($meta['title'], $keyphrase)
                ? $this->check('keyphrase_title', 'good', 'Keyphrase appears in the SEO title.')
                : $this->check('keyphrase_title', 'improve', 'Add the keyphrase to the SEO title.');
            $checks[] = $this->contains($meta['description'], $keyphrase)
                ? $this->check('keyphrase_description', 'good', 'Keyphrase appears in the meta description.')
                : $this->check('keyphrase_description', 'improve', 'Use the keyphrase once in the meta description.');
            $checks[] = $this->contains($subject->firstParagraph, $keyphrase)
                ? $this->check('keyphrase_intro', 'good', 'Keyphrase appears in the first paragraph.')
                : $this->check('keyphrase_intro', 'improve', 'Mention the keyphrase in the first paragraph.');

            if (! $subject->isHome) {
                $slugWords = array_filter(explode('-', Str::slug($keyphrase)), fn (string $w): bool => strlen($w) > 2);
                $inSlug = $slugWords !== [] && collect($slugWords)->filter(fn (string $w): bool => str_contains($subject->path, $w))->count() >= (int) ceil(count($slugWords) / 2);
                $checks[] = $inSlug
                    ? $this->check('keyphrase_slug', 'good', 'The address contains the keyphrase.')
                    : $this->check('keyphrase_slug', 'improve', 'Use words from the keyphrase in the address.');
            }

            $checks[] = collect($subject->headings)->contains(fn (string $h): bool => $this->contains($h, $keyphrase))
                ? $this->check('keyphrase_heading', 'good', 'Keyphrase appears in a subheading.')
                : $this->check('keyphrase_heading', 'improve', 'Add the keyphrase to one H2.');

            $density = $uses * count(explode(' ', $keyphrase)) / $words * 100;
            $checks[] = match (true) {
                $uses === 0 => $this->check('keyphrase_density', 'problem', 'The keyphrase doesn’t appear in the text yet.'),
                $density > 3 => $this->check('keyphrase_density', 'improve', "The keyphrase is used {$uses}× — that reads as stuffed. Use it less."),
                default => $this->check('keyphrase_density', 'good', "Keyphrase used {$uses}× on the page."),
            };

            $checks[] = in_array($keyphrase, array_map('mb_strtolower', $site['focus_elsewhere']), true)
                ? $this->check('keyphrase_unique', 'problem', 'Another page already uses this focus keyphrase. Pick a different one so they don’t compete.')
                : $this->check('keyphrase_unique', 'good', 'Not used as focus on any other page.');

            $checks[] = collect($subject->images)->contains(fn (array $i): bool => $this->contains($i['alt'].' '.str_replace(['-', '_'], ' ', $i['filename']), $keyphrase))
                ? $this->check('keyphrase_image', 'good', 'An image’s alt text or file name includes the keyphrase.')
                : ($subject->images === [] ? $this->check('keyphrase_image', 'improve', 'Add an image to the page.') : $this->check('keyphrase_image', 'improve', 'Use the keyphrase in one image’s alt text or file name.'));
        }

        // Title and description.
        $titleChars = mb_strlen($meta['title']);
        $checks[] = match (true) {
            $titleChars === 0 => $this->check('title_length', 'problem', 'Add an SEO title.'),
            $titlePx > self::TITLE_MAX_PX => $this->check('title_length', 'problem', "SEO title is {$titleChars} characters — shorten to about 60."),
            $titleChars < 30 => $this->check('title_length', 'improve', 'The SEO title is short; there is room to describe the page.'),
            default => $this->check('title_length', 'good', 'SEO title length is good.'),
        };
        $descriptionChars = mb_strlen($meta['description']);
        $checks[] = match (true) {
            $descriptionChars === 0 => $this->check('description_length', 'problem', 'Write a meta description; otherwise Google picks text from the page.'),
            $descriptionPx > self::DESCRIPTION_MAX_PX => $this->check('description_length', 'improve', "Meta description is {$descriptionChars} characters — Google will cut it. Aim for 120–155."),
            $descriptionChars < 100 => $this->check('description_length', 'improve', 'Meta description is short; aim for 120–155 characters.'),
            default => $this->check('description_length', 'good', 'Meta description length is good.'),
        };

        // Content.
        $missingAlt = count(array_filter($subject->images, fn (array $i): bool => trim($i['alt']) === ''));
        if ($subject->images !== []) {
            $checks[] = $missingAlt === 0
                ? $this->check('image_alt', 'good', 'All '.count($subject->images).' images have alt text.')
                : $this->check('image_alt', 'problem', $missingAlt === 1 ? 'One image has no alt text.' : "{$missingAlt} images have no alt text.");
        }

        $internal = count(array_filter($subject->links, fn (string $href): bool => str_starts_with($href, '/') && ! str_starts_with($href, '//') || str_starts_with($href, '#')));
        $external = count(array_filter($subject->links, fn (string $href): bool => str_starts_with($href, 'https://') || str_starts_with($href, 'http://')));
        $checks[] = $internal > 0
            ? $this->check('internal_links', 'good', "Links to {$internal} other ".($internal === 1 ? 'place' : 'places').' on the site.')
            : $this->check('internal_links', 'improve', 'Add an internal link to a related page.');
        $checks[] = $external > 0
            ? $this->check('outbound_links', 'good', 'Cites an outside source.')
            : $this->check('outbound_links', 'improve', 'Link to a trusted outside source (NIMH, FDA) to back up health claims.');

        if ($site['incoming_links'] !== null) {
            $checks[] = $site['incoming_links'] > 0 || $subject->isHome
                ? $this->check('incoming_links', 'good', 'Linked from '.max(1, $site['incoming_links']).' other '.($site['incoming_links'] === 1 ? 'page' : 'pages').'.')
                : $this->check('incoming_links', 'improve', 'No other page links here yet.');
        }

        $checks[] = $words >= 300
            ? $this->check('text_length', 'good', "The page has {$words} words.")
            : $this->check('text_length', 'improve', "The page has {$words} words; 300 or more helps search engines understand it.");

        if (! $meta['index']) {
            $checks[] = $this->check('noindex', 'problem', 'This page is hidden from search results (noindex).');
        }

        $readability = $this->readability($subject);

        return [
            'score' => $this->score($checks),
            'readability' => $this->score($readability),
            'seo' => $this->ordered($checks),
            'readability_checks' => $this->ordered($readability),
            'title' => ['chars' => $titleChars, 'px' => $titlePx, 'max_px' => self::TITLE_MAX_PX],
            'description' => ['chars' => $descriptionChars, 'px' => $descriptionPx, 'max_px' => self::DESCRIPTION_MAX_PX],
            'keyphrase_uses' => $uses,
        ];
    }

    /** @return list<array{key: string, status: string, text: string}> */
    private function readability(SeoSubject $subject): array
    {
        $text = trim($subject->bodyText);

        if ($text === '') {
            return [$this->check('readability_text', 'problem', 'There is no text to read yet.')];
        }

        $sentences = array_values(array_filter(preg_split('/(?<=[.!?])\s+/', $text) ?: [], fn (string $s): bool => str_word_count($s) > 0));
        $sentenceCount = max(1, count($sentences));
        $words = max(1, str_word_count($text));
        $syllables = array_sum(array_map(fn (string $w): int => self::syllables($w), str_word_count(mb_strtolower($text), 1)));
        $flesch = (int) round(206.835 - 1.015 * ($words / $sentenceCount) - 84.6 * ($syllables / $words));
        $long = count(array_filter($sentences, fn (string $s): bool => str_word_count($s) > 20)) / $sentenceCount * 100;
        $passive = count(array_filter($sentences, fn (string $s): bool => preg_match('/\b(am|is|are|was|were|be|been|being)\s+\w+(ed|en)\b/i', $s) === 1)) / $sentenceCount * 100;
        $paragraphs = preg_split('/\n\s*\n/', $text) ?: [];
        $longParagraphs = count(array_filter($paragraphs, fn (string $p): bool => str_word_count($p) > 150));

        $checks = [
            match (true) {
                $flesch >= 60 => $this->check('flesch', 'good', "Reading ease {$flesch}: easy to follow."),
                $flesch >= 45 => $this->check('flesch', 'improve', "Reading ease {$flesch}: fairly difficult. Shorter words and sentences help."),
                default => $this->check('flesch', 'problem', "Reading ease {$flesch}: hard to read. Use shorter sentences and plainer words."),
            },
            $long <= 25
                ? $this->check('sentence_length', 'good', 'Sentences are a comfortable length.')
                : $this->check('sentence_length', 'improve', round($long).'% of sentences are longer than 20 words. Aim for 25% or less.'),
            $passive <= 10
                ? $this->check('passive_voice', 'good', 'Little passive voice.')
                : $this->check('passive_voice', 'improve', round($passive).'% of sentences use the passive voice. Aim for 10% or less.'),
            $longParagraphs === 0
                ? $this->check('paragraph_length', 'good', 'Paragraphs are short enough to scan.')
                : $this->check('paragraph_length', 'improve', $longParagraphs === 1 ? 'One paragraph is longer than 150 words.' : "{$longParagraphs} paragraphs are longer than 150 words."),
        ];

        if ($words > 300) {
            $checks[] = count($subject->headings) >= (int) floor($words / 300)
                ? $this->check('subheadings', 'good', 'Subheadings break the text up well.')
                : $this->check('subheadings', 'improve', 'Add a subheading every 300 words or so.');
        }

        return $checks;
    }

    /**
     * Estimated rendered width in Google's results (Arial 20 px titles,
     * 14 px descriptions). An estimate, like every SEO tool's.
     */
    public static function pixels(string $text, string $kind): int
    {
        $size = $kind === 'title' ? 18.8 : 12.4;
        $width = 0.0;

        foreach (mb_str_split($text) as $char) {
            $width += match (true) {
                str_contains("ijlI.,:;'|!", $char) => 0.25,
                $char === ' ' => 0.28,
                str_contains('ftr()[]-', $char) => 0.33,
                str_contains('mwMW', $char) => 0.83,
                ctype_upper($char) => 0.67,
                ctype_digit($char) => 0.56,
                default => 0.52,
            };
        }

        return (int) round($width * $size);
    }

    public static function syllables(string $word): int
    {
        $word = preg_replace('/[^a-z]/', '', $word) ?? '';

        if ($word === '') {
            return 0;
        }

        $word = preg_replace('/(?:[^laeiouy]es|ed|[^laeiouy]e)$/', '', $word) ?? $word;
        $word = preg_replace('/^y/', '', $word) ?? $word;

        return max(1, preg_match_all('/[aeiouy]{1,2}/', $word));
    }

    private function contains(string $haystack, string $phrase): bool
    {
        return $phrase !== '' && $this->countPhrase(mb_strtolower($haystack), $phrase) > 0;
    }

    private function countPhrase(string $haystack, string $phrase): int
    {
        return (int) preg_match_all('/(?<![\p{L}\p{N}])'.preg_quote($phrase, '/').'(?![\p{L}\p{N}])/u', $haystack);
    }

    /** @return array{key: string, status: string, text: string} */
    private function check(string $key, string $status, string $text): array
    {
        return ['key' => $key, 'status' => $status, 'text' => $text];
    }

    /** @param list<array{key: string, status: string, text: string}> $checks */
    private function score(array $checks): int
    {
        if ($checks === []) {
            return 0;
        }

        $points = array_sum(array_map(fn (array $c): float => match ($c['status']) {
            'good' => 1.0,
            'improve' => 0.5,
            default => 0.0,
        }, $checks));

        return (int) round($points / count($checks) * 100);
    }

    /**
     * Problems first, then improvements, then what is already good.
     *
     * @param  list<array{key: string, status: string, text: string}>  $checks
     * @return list<array{key: string, status: string, text: string}>
     */
    private function ordered(array $checks): array
    {
        $rank = ['problem' => 0, 'improve' => 1, 'good' => 2];
        usort($checks, fn (array $a, array $b): int => $rank[$a['status']] <=> $rank[$b['status']]);

        return $checks;
    }
}
