<?php

declare(strict_types=1);

use Uptms\Seo\Services\TitleTemplate;

// Reads the site name and separator from seo_settings, so it needs the database.
it('drops empty template variables with their separator', function () {
    $titles = app(TitleTemplate::class);

    expect($titles->render('%title% %sep% %category% %sep% %site%', ['title' => 'A TMS session']))->toBe('A TMS session | UPTMS')
        ->and($titles->render('%title% %sep% %category% %sep% %site%', ['title' => 'A TMS session', 'category' => 'Guides']))->toBe('A TMS session | Guides | UPTMS');
});
