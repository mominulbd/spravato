<?php

declare(strict_types=1);

namespace Uptms\Publishing\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Jobs\SyncJob;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use JsonException;

/**
 * Tells the website (uptms-app `/api/revalidate`) which cache tags changed.
 * The body is signed with HMAC-SHA256 over `{timestamp}.{body}` using
 * FRONTEND_REVALIDATE_SECRET, so nobody else can flush the site's cache.
 * Without a secret (local set-ups) the call is skipped; pages then refresh
 * when their cache life ends.
 */
final class RevalidateFrontend implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 4;

    /** @var list<int> */
    public array $backoff = [5, 30, 120];

    /**
     * @param  list<string>  $tags
     */
    public function __construct(public readonly array $tags)
    {
        $this->afterCommit();
    }

    /**
     * @throws JsonException
     */
    public function handle(): void
    {
        $url = config('services.frontend.url');
        $secret = config('services.frontend.revalidate_secret');

        if (! is_string($url) || $url === '' || ! is_string($secret) || $secret === '') {
            Log::info('Website revalidation skipped: FRONTEND_URL or FRONTEND_REVALIDATE_SECRET is not set.', ['tags' => $this->tags]);

            return;
        }

        $body = json_encode(['tags' => array_values(array_unique($this->tags))], JSON_THROW_ON_ERROR);
        $timestamp = (string) now()->getTimestamp();

        try {
            Http::timeout(5)
                ->withHeaders([
                    'X-Uptms-Timestamp' => $timestamp,
                    'X-Uptms-Signature' => self::sign($timestamp, $body, $secret),
                ])
                ->withBody($body, 'application/json')
                ->post(rtrim($url, '/').'/api/revalidate')
                ->throw();
        } catch (ConnectionException|RequestException $exception) {
            // On a real queue, fail so the job retries with backoff. Run inline
            // (sync queue), the publish already succeeded: don't turn it into an
            // error for the editor; the site refreshes within its cache life.
            if ($this->job !== null && ! $this->job instanceof SyncJob) {
                throw $exception;
            }

            Log::warning('Website revalidation failed; pages refresh within an hour.', ['tags' => $this->tags, 'error' => $exception->getMessage()]);
        }
    }

    public static function sign(string $timestamp, string $body, string $secret): string
    {
        return hash_hmac('sha256', $timestamp.'.'.$body, $secret);
    }
}
