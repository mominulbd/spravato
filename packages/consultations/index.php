<?php

declare(strict_types=1);

use Uptms\Consultations\ConsultationsServiceProvider;

return [
    'id' => 'uptms.consultations',
    'name' => 'Consultations',
    'description' => 'Consultation requests from the website (Figma 03–05): encrypted contact details, the call-back workflow, assignment, notes, view logging, staff alerts (the request itself or a link only, per Clinic settings → Privacy), a receipt emailed to the person, export and retention.',
    'autoload' => [
        'Uptms\\Consultations\\' => 'src/',
        'Uptms\\Consultations\\Factories\\' => 'factories/',
        'Uptms\\Consultations\\Seeders\\' => 'seeders/',
    ],
    'provider' => ConsultationsServiceProvider::class,
    'require' => ['uptms.audit_log', 'uptms.permissions', 'uptms.users', 'uptms.settings', 'uptms.publishing'],
];
