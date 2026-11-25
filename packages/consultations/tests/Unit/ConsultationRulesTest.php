<?php

declare(strict_types=1);

use Uptms\Consultations\Domain\Enums\ConsultationStatus;
use Uptms\Consultations\Domain\Enums\ConsultationTopic;
use Uptms\Consultations\Models\ConsultationRequest;
use Uptms\Consultations\Services\BlindIndex;
use Uptms\Consultations\Services\ConsultationPresenter;
use Uptms\Consultations\Services\SourceDescriber;
use Uptms\Consultations\Services\StaffAlertDetails;

it('allows only the documented status moves', function (string $from, string $to, bool $allowed) {
    expect(ConsultationStatus::from($from)->canTransitionTo(ConsultationStatus::from($to)))->toBe($allowed);
})->with([
    'new → contacted' => ['new', 'contacted', true],
    'contacted → new (undo)' => ['contacted', 'new', true],
    'scheduled → new' => ['scheduled', 'new', false],
    'closed → contacted (reopen)' => ['closed', 'contacted', true],
    'closed → spam' => ['closed', 'spam', false],
    'spam → new (not spam)' => ['spam', 'new', true],
    'spam → closed' => ['spam', 'closed', false],
    'new → new' => ['new', 'new', false],
]);

it('masks contact details for lists', function () {
    expect(ConsultationPresenter::maskName('Daniel  Reyes'))->toBe('Daniel R.')
        ->and(ConsultationPresenter::maskName('Cher'))->toBe('Cher')
        ->and(ConsultationPresenter::maskName('Ana María de la Cruz'))->toBe('Ana C.')
        ->and(ConsultationPresenter::maskPhone('+1 253-555-0198'))->toBe('(253) •••-0198');
});

it('builds keyed search tokens that never contain the plaintext', function () {
    $index = new BlindIndex;
    $built = $index->build('José Reyes', '(253) 555-0198', 'J.Reyes@Example.com');

    expect($built)->not->toContain('reyes')->not->toContain('0198')
        ->and($index->query('jose'))->toHaveCount(1)
        ->and(str_contains($built, $index->query('jose')[0]))->toBeTrue()
        ->and(str_contains($built, $index->query('253-555-0198')[0]))->toBeTrue()
        ->and(str_contains($built, $index->query('0198')[0]))->toBeTrue()
        ->and(str_contains($built, $index->query('j.reyes@example.com')[0]))->toBeTrue()
        ->and($index->query('555'))->toBe([])
        ->and($index->phoneHash('1-253-555-0198'))->toBe($index->phoneHash('(253) 555 0198'));
});

it('describes where a request came from in coarse labels', function () {
    $describer = new SourceDescriber;

    expect($describer->device('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/129.0 Safari/537.36'))->toBe('Desktop · Chrome')
        ->and($describer->referrerHost('https://www.google.com/search?q=tms+tacoma', 'uptms.com'))->toBe('google.com')
        ->and($describer->referrerHost('https://uptms.com/tms', 'uptms.com'))->toBeNull()
        ->and($describer->previousPage('https://www.uptms.com/conditions/ocd?x=1', 'uptms.com'))->toBe('/conditions/ocd')
        ->and($describer->firstTouch('google.com', null))->toBe('Google · organic')
        ->and($describer->firstTouch(null, ['source' => 'newsletter', 'medium' => 'email']))->toBe('Newsletter · email')
        ->and($describer->firstTouch(null, null))->toBe('Direct');
});

it('spells a request out for the staff alert and says so when an answer is missing', function () {
    $request = new ConsultationRequest([
        'name' => 'Daniel Reyes',
        'phone' => '(253) 555-0198',
        'insurance' => 'Premera Blue Cross',
        'topic' => ConsultationTopic::OcdSpecialty->value,
    ]);

    expect((new StaffAlertDetails)->lines($request))->toBe([
        'Name: Daniel Reyes',
        'Phone: (253) 555-0198',
        'Email: Not given',
        'Preferred contact: Not given',
        'Best time to call: Not given',
        'Asking about: Deep TMS for OCD',
        'Insurance: Premera Blue Cross',
        'Therapy or antidepressants tried: Not given',
    ]);
});
