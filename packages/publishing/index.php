<?php

declare(strict_types=1);

use Uptms\Publishing\PublishingServiceProvider;

return [
    'id' => 'uptms.publishing',
    'name' => 'Publishing',
    'description' => 'What every content module shares: publish status, revisions, the public read cache, block data sources and the revalidation webhook to the website.',
    'autoload' => [
        'Uptms\\Publishing\\' => 'src/',
    ],
    'provider' => PublishingServiceProvider::class,
    'require' => ['uptms.audit_log', 'uptms.permissions'],
];
