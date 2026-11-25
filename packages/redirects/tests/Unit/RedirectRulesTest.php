<?php

declare(strict_types=1);

use Uptms\Redirects\Services\NotFoundMonitor;
use Uptms\Redirects\Services\RedirectRules;

it('normalises addresses the way the proxy matches them', function () {
    expect(RedirectRules::normalise('https://uptms.com/OCD-Treatment/?utm=x#top'))->toBe('/ocd-treatment')
        ->and(RedirectRules::normalise('/'))->toBe('/')
        ->and(RedirectRules::normalise('/blog/*'))->toBe('/blog/*')
        ->and(RedirectRules::normaliseTarget('/TMS/#how'))->toBe('/tms#how')
        ->and(RedirectRules::normaliseTarget('https://example.org/A'))->toBe('https://example.org/A');
});

it('scores likely targets above unrelated pages', function () {
    expect(NotFoundMonitor::likeness('/ocd-treatment', '/conditions/ocd'))->toBeGreaterThan(60)
        ->and(NotFoundMonitor::likeness('/conditions/anxiety', '/conditions/anxious-depression'))->toBeGreaterThanOrEqual(50)
        ->and(NotFoundMonitor::likeness('/conditions/ocd-treatment', '/conditions/depression'))->toBeLessThan(50)
        ->and(NotFoundMonitor::likeness('/images/old-logo.png', '/tms'))->toBeLessThan(20)
        ->and(NotFoundMonitor::looksLikeBot('/wp-login.php'))->toBeTrue()
        ->and(NotFoundMonitor::looksLikeBot('/conditions/ocd'))->toBeFalse();
});
