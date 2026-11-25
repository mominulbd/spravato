<?php

declare(strict_types=1);

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Uptms\Publishing\Jobs\RevalidateFrontend;

beforeEach(fn () => config(['services.frontend.url' => 'http://site.test', 'services.frontend.revalidate_secret' => 's3cret']));

it('posts the tags signed with HMAC over timestamp and body', function () {
    Http::fake(['site.test/*' => Http::response(['revalidated' => true])]);

    RevalidateFrontend::dispatchSync(['content', 'page:home']);

    Http::assertSent(function (Request $request): bool {
        $timestamp = $request->header('X-Uptms-Timestamp')[0];

        return $request->url() === 'http://site.test/api/revalidate'
            && $request->body() === '{"tags":["content","page:home"]}'
            && $request->header('X-Uptms-Signature')[0] === hash_hmac('sha256', $timestamp.'.'.$request->body(), 's3cret');
    });
});

it('skips without a secret and never breaks an inline publish when the site is down', function () {
    Http::fake(['site.test/*' => fn () => throw new ConnectionException('down')]);
    RevalidateFrontend::dispatchSync(['content']);

    config(['services.frontend.revalidate_secret' => null]);
    RevalidateFrontend::dispatchSync(['content']);

    Http::assertSentCount(0);
});
