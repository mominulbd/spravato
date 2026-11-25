<?php

declare(strict_types=1);

use Uptms\Sample\SampleServiceProvider;

/*
 * Fixture module for tests/Feature/ModuleConventionsTest.php. It is not under
 * /packages, so the app never discovers it; the test registers it by hand.
 */
return [
    'id' => 'uptms.sample',
    'name' => 'Sample',
    'description' => 'Proves the ModuleServiceProvider route conventions.',
    'autoload' => [
        'Uptms\\Sample\\' => 'src/',
    ],
    'provider' => SampleServiceProvider::class,
];
