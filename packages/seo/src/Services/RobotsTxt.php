<?php

declare(strict_types=1);

namespace Uptms\Seo\Services;

/**
 * robots.txt as edited in Figma 22, and its "Test a URL" check for
 * Googlebot: the longest matching Allow/Disallow rule wins (Google's
 * rules), `*` matches anything and `$` anchors the end.
 */
final class RobotsTxt
{
    /**
     * @return array{allowed: bool, line: int|null, rule: string|null}
     */
    public function test(string $content, string $path, string $agent = 'googlebot'): array
    {
        $path = '/'.ltrim((string) preg_replace('#^https?://[^/]+#i', '', trim($path)), '/');
        $groups = $this->groups($content);
        $rules = $groups[mb_strtolower($agent)] ?? $groups['*'] ?? [];
        $best = null;

        foreach ($rules as $rule) {
            if ($rule['pattern'] === '' && $rule['allow'] === false) {
                continue; // "Disallow:" with no value allows everything.
            }

            if ($this->matches($rule['pattern'], $path) && ($best === null || strlen($rule['pattern']) > strlen($best['pattern']) || (strlen($rule['pattern']) === strlen($best['pattern']) && $rule['allow']))) {
                $best = $rule;
            }
        }

        return [
            'allowed' => $best === null || $best['allow'],
            'line' => $best['line'] ?? null,
            'rule' => $best === null ? null : ($best['allow'] ? 'Allow: ' : 'Disallow: ').$best['pattern'],
        ];
    }

    /** @return array<string, list<array{allow: bool, pattern: string, line: int}>> agent → rules */
    private function groups(string $content): array
    {
        $groups = [];
        $agents = [];
        $collecting = false;

        foreach (preg_split('/\r\n|\r|\n/', $content) ?: [] as $index => $raw) {
            $line = trim((string) preg_replace('/#.*$/', '', $raw));

            if (! str_contains($line, ':')) {
                continue;
            }

            [$field, $value] = array_map('trim', explode(':', $line, 2));
            $field = mb_strtolower($field);

            if ($field === 'user-agent') {
                $agents = $collecting ? [mb_strtolower($value)] : [...$agents, mb_strtolower($value)];
                $collecting = false;

                continue;
            }

            if ($field === 'allow' || $field === 'disallow') {
                $collecting = true;

                foreach ($agents as $agent) {
                    $groups[$agent][] = ['allow' => $field === 'allow', 'pattern' => $value, 'line' => $index + 1];
                }
            }
        }

        return $groups;
    }

    private function matches(string $pattern, string $path): bool
    {
        $anchored = str_ends_with($pattern, '$');
        $regex = preg_quote(rtrim($pattern, '$'), '#');
        $regex = str_replace('\*', '.*', $regex);

        return preg_match('#^'.$regex.($anchored ? '$' : '').'#', $path) === 1;
    }
}
