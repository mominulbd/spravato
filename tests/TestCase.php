<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Requests look like they come from the admin SPA, so Sanctum treats
        // them as stateful: sessions and encrypted cookies work exactly as in
        // the browser.
        $this->withHeader('Referer', 'http://localhost/admin');
    }
}
