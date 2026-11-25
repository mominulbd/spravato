<?php

declare(strict_types=1);

use Uptms\Auth\Services\Totp;

// RFC 6238 appendix B, SHA-1 secret "12345678901234567890", last 6 digits.
it('matches the RFC 6238 test vectors', function (int $time, string $code) {
    $totp = new Totp;
    $secret = $totp->base32Encode('12345678901234567890');

    expect($secret)->toBe('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ')
        ->and($totp->code($secret, $totp->timestep($time)))->toBe($code);
})->with([
    [59, '287082'],
    [1111111109, '081804'],
    [1234567890, '005924'],
    [2000000000, '279037'],
]);

it('accepts the neighbouring step and nothing further', function () {
    $totp = new Totp;
    $secret = $totp->generateSecret();
    $now = 1_800_000_000;
    $previous = $totp->code($secret, $totp->timestep($now) - 1);
    $stale = $totp->code($secret, $totp->timestep($now) - 3);

    expect(strlen($secret))->toBe(32)
        ->and($totp->verify($secret, $previous, unixTime: $now))->toBe($totp->timestep($now) - 1)
        ->and($totp->verify($secret, $stale, unixTime: $now))->toBeNull()
        ->and($totp->verify($secret, 'abcdef', unixTime: $now))->toBeNull();
});

it('round-trips base32 and builds an otpauth URI', function () {
    $totp = new Totp;

    expect($totp->base32Decode($totp->base32Encode('hello world!')))->toBe('hello world!')
        ->and($totp->uri('ABC', 'joy@uptms.test', 'UPTMS'))
        ->toBe('otpauth://totp/UPTMS:joy%40uptms.test?secret=ABC&issuer=UPTMS&algorithm=SHA1&digits=6&period=30');
});
