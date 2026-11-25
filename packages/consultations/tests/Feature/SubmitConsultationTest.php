<?php

declare(strict_types=1);

use Database\Seeders\SiteContentSeeder;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Uptms\AuditLog\Models\AuditLog;
use Uptms\Consultations\Events\ConsultationRequestSubmitted;
use Uptms\Consultations\Listeners\SendPatientConfirmation;
use Uptms\Consultations\Models\ConsultationRequest;
use Uptms\Consultations\Notifications\ConsultationReceivedNotification;
use Uptms\Consultations\Notifications\NewConsultationRequestNotification;
use Uptms\Settings\Services\Settings;

function consultationPayload(array $overrides = []): array
{
    return [
        'first_name' => 'Daniel',
        'last_name' => 'Reyes',
        'phone' => '(253) 555-0198',
        'email' => 'd.reyes@example.com',
        'insurance' => 'Premera Blue Cross',
        'prior_treatment' => 'both',
        'contact_method' => 'text',
        'topic' => 'provider_referral',
        'source_page' => '/contact',
        'referrer' => 'http://localhost:3000/tms',
        ...$overrides,
    ];
}

beforeEach(function () {
    RateLimiter::clear('public-forms');
    Notification::fake();
});

it('stores a request encrypted and answers with the reference only', function () {
    $response = $this->postJson('/api/v1/public/consultation-requests', consultationPayload(), ['User-Agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 Version/18.0 Mobile/15E148 Safari/604.1'])
        ->assertCreated()
        ->assertJsonStructure(['data' => ['reference']]);

    $request = ConsultationRequest::query()->sole();
    expect($response->json('data'))->toBe(['reference' => $request->reference])
        ->and($request->reference)->toMatch('/^CR-\d{4}-\d{4,}$/')
        ->and($request->status->value)->toBe('new')
        ->and($request->name)->toBe('Daniel Reyes')
        ->and($request->insurance)->toBe('Premera Blue Cross')
        ->and($request->prior_treatment)->toBe('both')
        ->and($request->contact_method?->value)->toBe('text')
        ->and($request->previous_page)->toBe('/tms')
        ->and($request->device)->toBe('Mobile · Safari')
        ->and($request->follow_up_at?->isFuture())->toBeTrue();

    // Encryption at rest: the raw columns hold no plaintext.
    $raw = (array) DB::table('consultation_requests')->where('id', $request->id)->first();
    foreach (['name' => 'Daniel', 'phone' => '0198', 'email' => 'reyes', 'insurance' => 'Premera', 'prior_treatment' => 'both'] as $column => $fragment) {
        expect((string) $raw[$column])->not->toContain($fragment);
    }
    expect((string) $raw['search_index'])->not->toContain('daniel');

    // The audit trail records the submission without patient details.
    $audit = AuditLog::query()->where('action', 'consultation.submitted')->sole();
    expect(json_encode([$audit->old_values, $audit->new_values, $audit->auditable_label]))->not->toContain('Daniel')->not->toContain('0198');
});

it('alerts staff with the request when Privacy says to include it', function () {
    $this->postJson('/api/v1/public/consultation-requests', consultationPayload(['message' => 'Mornings are easiest.']))->assertCreated();
    $request = ConsultationRequest::query()->sole();

    Notification::assertSentOnDemand(NewConsultationRequestNotification::class, function (NewConsultationRequestNotification $notification, array $channels, object $notifiable) use ($request): bool {
        $mail = $notification->toMail($notifiable);
        $text = (string) json_encode([$mail->subject, $mail->greeting, $mail->introLines, $mail->outroLines]);

        return $notifiable->routes['mail'] === 'contact@uptms.com'
            && $mail->actionUrl === NewConsultationRequestNotification::url($request->id)
            && str_contains($text, 'Name: Daniel Reyes')
            && str_contains($text, '(253) 555-0198')
            && str_contains($text, 'd.reyes@example.com')
            && str_contains($text, 'Premera Blue Cross')
            && str_contains($text, 'Text message')
            && str_contains($text, 'Provider referral')
            && str_contains($text, 'Yes, both therapy and antidepressants')
            && str_contains($text, 'Mornings are easiest.')
            // The reference, not the answers, is all the subject carries.
            && $mail->subject === 'New consultation request '.$request->reference;
    });

    // The activity note records that the alert carried the request, never what it said.
    $audit = AuditLog::query()->where('action', 'consultation.alert_sent')->sole();
    expect($audit->new_values)->toBe(['details' => true])
        ->and(json_encode($audit->new_values))->not->toContain('Daniel');
});

it('alerts staff with a link and no patient details when Privacy says so', function () {
    app(Settings::class)->update('privacy', ['staff_alert_details' => false]);

    $this->postJson('/api/v1/public/consultation-requests', consultationPayload())->assertCreated();
    $request = ConsultationRequest::query()->sole();

    Notification::assertSentOnDemand(NewConsultationRequestNotification::class, function (NewConsultationRequestNotification $notification, array $channels, object $notifiable) use ($request): bool {
        $mail = $notification->toMail($notifiable);
        $text = json_encode([$mail->subject, $mail->greeting, $mail->introLines, $mail->outroLines, $mail->actionUrl]);

        return $notifiable->routes['mail'] === 'contact@uptms.com'
            && $mail->actionUrl === NewConsultationRequestNotification::url($request->id)
            && ! str_contains((string) $text, 'Daniel')
            && ! str_contains((string) $text, '0198')
            && ! str_contains((string) $text, 'referral')
            && ! str_contains((string) $text, 'TMS');
    });
    expect(AuditLog::query()->where('action', 'consultation.alert_sent')->sole()->new_values)->toBe(['details' => false]);
});

it('sends no alert when no notification email is set, and still sends the receipt', function () {
    app(Settings::class)->update('privacy', ['staff_notification_email' => '']);

    $this->postJson('/api/v1/public/consultation-requests', consultationPayload())->assertCreated();

    Notification::assertNotSentTo(new AnonymousNotifiable, NewConsultationRequestNotification::class);
    Notification::assertSentOnDemand(ConsultationReceivedNotification::class);
    expect(AuditLog::query()->where('action', 'consultation.alert_sent')->count())->toBe(0);
});

it('emails the person a receipt with the reference and nothing they typed', function () {
    $this->postJson('/api/v1/public/consultation-requests', consultationPayload())->assertCreated();
    $request = ConsultationRequest::query()->sole();

    Notification::assertSentOnDemand(ConsultationReceivedNotification::class, function (ConsultationReceivedNotification $notification, array $channels, object $notifiable) use ($request): bool {
        $mail = $notification->toMail($notifiable);
        $text = (string) json_encode([$mail->subject, $mail->greeting, $mail->introLines, $mail->outroLines]);

        return $notifiable->routes['mail'] === 'd.reyes@example.com'
            && str_contains($text, (string) $request->reference)
            && str_contains($text, '253-234-4199')
            && str_contains($text, '988')
            && ! str_contains($text, 'Daniel')
            && ! str_contains($text, '0198')
            && ! str_contains($text, 'Premera')
            && ! str_contains($text, 'referral');
    });
    expect(AuditLog::query()->where('action', 'consultation.confirmation_sent')->count())->toBe(1);
});

it('sends no receipt for a request without an email address', function () {
    $request = ConsultationRequest::factory()->create(['email' => null]);

    app(SendPatientConfirmation::class)->handle(new ConsultationRequestSubmitted($request->id));

    Notification::assertNothingSent();
    expect(AuditLog::query()->where('action', 'consultation.confirmation_sent')->count())->toBe(0);
});

it('validates in the patient\'s words', function () {
    $response = $this->postJson('/api/v1/public/consultation-requests', consultationPayload([
        'first_name' => '', 'last_name' => '', 'phone' => '555-01', 'email' => 'not-an-email', 'insurance' => '',
        'prior_treatment' => 'surgery', 'contact_method' => null, 'topic' => 'surgery',
    ]));

    assertErrorCode($response, 'VALIDATION_FAILED', 422)
        ->assertJsonPath('errors.first_name.0', 'Enter your first name so the team knows who to ask for.')
        ->assertJsonPath('errors.last_name.0', 'Enter your last name.')
        ->assertJsonPath('errors.phone.0', 'Enter a 10-digit U.S. phone number, e.g. (253) 555-0142.')
        ->assertJsonPath('errors.email.0', 'Enter a valid email address, e.g. you@example.com.')
        ->assertJsonPath('errors.insurance.0', 'Choose your insurance, or “Other” or “No insurance”.')
        ->assertJsonPath('errors.prior_treatment.0', 'Choose an answer from the list.')
        ->assertJsonPath('errors.contact_method.0', 'Choose how you would like the clinic to reach you.')
        ->assertJsonPath('errors.topic.0', 'Choose what you would like to talk about.');
    expect(ConsultationRequest::query()->count())->toBe(0);
});

it('files a request without a topic under TMS for depression', function () {
    $this->postJson('/api/v1/public/consultation-requests', consultationPayload(['topic' => null]))->assertCreated();

    expect(ConsultationRequest::query()->sole()->topic->value)->toBe('tms_depression');
});

it('quietly drops honeypot submissions', function () {
    $this->postJson('/api/v1/public/consultation-requests', consultationPayload(['website' => 'https://spam.example']))
        ->assertCreated()->assertJsonPath('data.reference', null);

    expect(ConsultationRequest::query()->count())->toBe(0);
    Notification::assertNothingSent();
});

it('returns the first request for a quick double submit', function () {
    $first = $this->postJson('/api/v1/public/consultation-requests', consultationPayload())->json('data.reference');
    $second = $this->postJson('/api/v1/public/consultation-requests', consultationPayload(['phone' => '253.555.0198']))->json('data.reference');

    expect($second)->toBe($first)->and(ConsultationRequest::query()->count())->toBe(1);
});

it('allows five posts a minute per visitor', function () {
    foreach (range(1, 5) as $i) {
        $this->postJson('/api/v1/public/consultation-requests', consultationPayload(['phone' => '(253) 555-010'.$i]))->assertCreated();
    }

    assertErrorCode($this->postJson('/api/v1/public/consultation-requests', consultationPayload(['phone' => '(253) 555-0199'])), 'RATE_LIMITED', 429);
});

it('rate limits by the visitor address the site forwards with its token', function () {
    config(['services.frontend.site_token' => 'site-secret']);

    foreach (range(1, 5) as $i) {
        $this->postJson('/api/v1/public/consultation-requests', consultationPayload(['phone' => '(253) 555-020'.$i]), [
            'X-Uptms-Site-Token' => 'site-secret', 'X-Uptms-Client-Ip' => '203.0.113.7',
        ])->assertCreated();
    }

    // Another visitor behind the same Next.js server is not affected …
    $this->postJson('/api/v1/public/consultation-requests', consultationPayload(['phone' => '(253) 555-0300']), [
        'X-Uptms-Site-Token' => 'site-secret', 'X-Uptms-Client-Ip' => '203.0.113.8',
    ])->assertCreated();

    // … while forged addresses without the token all count against the caller.
    foreach (range(1, 5) as $i) {
        $this->postJson('/api/v1/public/consultation-requests', consultationPayload(['phone' => '(253) 555-040'.$i]), [
            'X-Uptms-Site-Token' => 'wrong', 'X-Uptms-Client-Ip' => '198.51.100.'.$i,
        ])->assertCreated();
    }
    assertErrorCode($this->postJson('/api/v1/public/consultation-requests', consultationPayload(['phone' => '(253) 555-0410']), [
        'X-Uptms-Site-Token' => 'wrong', 'X-Uptms-Client-Ip' => '198.51.100.99',
    ]), 'RATE_LIMITED', 429);
});

it('requires a valid Turnstile token once a secret is configured', function () {
    config(['services.turnstile.secret_key' => 'turnstile-secret']);
    Http::fake(['challenges.cloudflare.com/*' => Http::sequence()
        ->push(['success' => false])
        ->push(['success' => true])]);

    assertErrorCode($this->postJson('/api/v1/public/consultation-requests', consultationPayload(['turnstile_token' => 'bad'])), 'VALIDATION_FAILED', 422)
        ->assertJsonValidationErrors('turnstile_token');
    $this->postJson('/api/v1/public/consultation-requests', consultationPayload(['turnstile_token' => 'good']))->assertCreated();
});

it('gives the website the form choices through the consultation block', function () {
    $this->seed(SiteContentSeeder::class);

    $blocks = $this->getJson('/api/v1/public/page?path=/contact')->assertOk()->json('data.blocks');
    $block = collect($blocks)->firstWhere('type', 'consultation');

    expect($block['sources']['form']['accepting'])->toBeTrue()
        ->and(array_column($block['sources']['form']['topics'], 'value'))->toBe(['tms_depression', 'insurance', 'ocd_specialty', 'loved_one', 'provider_referral'])
        ->and(array_column($block['sources']['form']['prior_treatments'], 'value'))->toBe(['both', 'therapy_only', 'medication_only', 'neither'])
        ->and(array_column($block['sources']['form']['contact_methods'], 'value'))->toBe(['phone', 'text', 'email'])
        ->and(array_column($block['sources']['insurers'], 'name'))->toContain('Premera Blue Cross')
        ->and($block['data']['submit_label'])->toBe('Request My Free Phone Consultation');

    // The Home hero's button opens the same form in a popup.
    $home = collect($this->getJson('/api/v1/public/page?path=%2F')->assertOk()->json('data.blocks'));
    $hero = $home->firstWhere('type', 'hero');
    expect($hero['data']['primary_opens_form'])->toBeTrue()
        ->and($hero['data']['form_title'])->toBe('Find out if you qualify for TMS')
        ->and($hero['data'])->not->toHaveKey('secondary')
        ->and($hero['sources']['form']['accepting'])->toBeTrue()
        ->and($hero['sources']['insurers'])->not->toBeEmpty();

    // So does "Talk with our team for free" in the panel below it.
    $panel = $home->firstWhere('type', 'copy_panel');
    expect($panel['data']['cta_opens_form'])->toBeTrue()
        ->and($panel['data']['form_title'])->toBe('Find out if you qualify for TMS')
        ->and($panel['sources']['form']['accepting'])->toBeTrue()
        ->and($panel['sources']['insurers'])->not->toBeEmpty();

    // And "Talk With Our Care Team" on the founder card, which keeps its team source.
    $team = $home->firstWhere('type', 'team');
    expect($team['data']['primary_opens_form'])->toBeTrue()
        ->and($team['data']['form_title'])->toBe('Find out if you qualify for TMS')
        ->and($team['sources']['team'])->not->toBeEmpty()
        ->and($team['sources']['form']['accepting'])->toBeTrue()
        ->and($team['sources']['insurers'])->not->toBeEmpty();

});
