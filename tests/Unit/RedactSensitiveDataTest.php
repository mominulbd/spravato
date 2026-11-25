<?php

declare(strict_types=1);

use App\Support\Logging\RedactSensitiveData;

it('redacts secrets and patient contact details at any depth', function (): void {
    $redacted = RedactSensitiveData::redact([
        'password' => 'hunter2',
        'request' => [
            'phone' => '253-555-0198',
            'email' => 'd.reyes@example.com',
            'message' => 'I would like to ask about TMS.',
            'totp_code' => '123456',
        ],
        'X-XSRF-TOKEN' => 'abc',
    ]);

    expect($redacted['password'])->toBe('[redacted]')
        ->and($redacted['request']['phone'])->toBe('[redacted]')
        ->and($redacted['request']['email'])->toBe('[redacted]')
        ->and($redacted['request']['message'])->toBe('[redacted]')
        ->and($redacted['request']['totp_code'])->toBe('[redacted]')
        ->and($redacted['X-XSRF-TOKEN'])->toBe('[redacted]');
});

it('keeps keys that only contain a sensitive word inside another word', function (): void {
    $redacted = RedactSensitiveData::redact([
        'telephone_country' => 'US',
        'keynote' => 'Deep TMS',
        'request_id' => '01J…',
    ]);

    expect($redacted)->toBe([
        'telephone_country' => 'US',
        'keynote' => 'Deep TMS',
        'request_id' => '01J…',
    ]);
});
