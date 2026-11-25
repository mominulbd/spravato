<?php

declare(strict_types=1);

return [
    // The clinic's time zone: office hours, the follow-up target and the
    // inbox's "today" are all reckoned here (University Place, WA).
    'timezone' => env('CLINIC_TIMEZONE', 'America/Los_Angeles'),

    // Key for the blind index that lets staff search encrypted names and
    // phone numbers. Defaults to a key derived from APP_KEY; rotating it
    // requires `php artisan consultations:reindex`.
    'index_key' => env('CONSULTATION_INDEX_KEY'),

    // A request still "new" after this many business days is overdue.
    'follow_up_business_days' => 1,

    // Spam is removed sooner than real requests.
    'spam_retention_days' => 30,

    // A second submission with the same phone number within this many
    // minutes returns the first request instead of creating a duplicate.
    'duplicate_window_minutes' => 10,
];
