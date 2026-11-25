<?php

declare(strict_types=1);

namespace Uptms\Seo\Services;

/**
 * `%title% %sep% %site%` → "Deep TMS therapy | UPTMS". Unknown or empty
 * variables drop out together with the separator next to them, so a post
 * without a category never shows "| |".
 */
final readonly class TitleTemplate
{
    public function __construct(private SeoSettings $settings) {}

    /** @param array<string, string> $vars */
    public function render(string $template, array $vars): string
    {
        $separator = (string) $this->settings->get('separator');
        $values = ['site' => (string) $this->settings->get('site_name'), ...$vars];

        $parts = preg_split('/\s*%sep%\s*/', $template) ?: [$template];
        $rendered = [];

        foreach ($parts as $part) {
            $text = trim((string) preg_replace_callback('/%([a-z_]+)%/', fn (array $m): string => $values[$m[1]] ?? '', $part));
            $text = (string) preg_replace('/\s{2,}/', ' ', $text);

            if ($text !== '') {
                $rendered[] = $text;
            }
        }

        return implode(' '.$separator.' ', $rendered);
    }

    /**
     * The template configured for a kind of content (page, condition, post).
     *
     * @param  array<string, string>  $vars
     */
    public function for(string $templateKey, string $title, array $vars = []): string
    {
        $template = $this->settings->get('template_'.$templateKey);

        return $this->render(is_string($template) ? $template : '%title% %sep% %site%', ['title' => $title, ...$vars]);
    }
}
