<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Uptms\AuditLog\Services\AuditRecorder;
use Uptms\Consultations\Domain\Enums\BestTime;
use Uptms\Consultations\Domain\Enums\ConsultationStatus;
use Uptms\Consultations\Models\ConsultationNote;
use Uptms\Consultations\Models\ConsultationRequest;
use Uptms\Users\Models\User;

/**
 * Local only: a believable inbox (the people of Figma 04) so the dashboard
 * and the consultation screens can be reviewed. Fictional people, 555
 * numbers, example.com addresses. Runs once; an inbox with requests is left alone.
 */
class DemoConsultationSeeder extends Seeder
{
    /** name, phone, topic, status, assignee email, minutes ago, email, message, button */
    private const array REQUESTS = [
        ['Anna Wright', '(253) 555-1120', 'spravato', 'closed', 'ayub@uptms.test', 2 * 1440 + 380, 'anna.w@example.com', null, 'Request a consultation'],
        ['Daniel Reyes', '(253) 555-0198', 'eligibility', 'new', null, 2 * 1440 + 65, 'd.reyes@example.com', 'My primary care doctor suggested I ask about Spravato. I’d like to understand the first appointment and whether my insurance (Premera) usually covers it.', 'Discuss your eligibility'],
        ['Maria Lopez', '(253) 555-4471', 'insurance', 'new', 'joy@uptms.test', 1440 + 370, null, 'Does my plan cover Spravato? I have Regence.', 'Ask about coverage & cost'],
        ['Linda Rowe', '(253) 555-3367', 'first_visit', 'scheduled', 'joy@uptms.test', 1440 + 290, 'linda.rowe@example.com', null, 'Talk through your first visit'],
        ['Keith Stone', '(253) 555-9054', 'question', 'contacted', 'cathy@uptms.test', 1440 + 120, null, 'Asking for my son, who is 22.', 'Ask the team a question →'],
        ['Helen Park', '(360) 555-2210', 'question', 'new', 'cathy@uptms.test', 1440 + 130, 'helen.park@example.com', null, 'Start a conversation →'],
        ['Kevin Tran', '(206) 555-8810', 'insurance', 'new', 'joy@uptms.test', 114, null, null, 'Ask about coverage & cost'],
        ['Sarah Mills', '(253) 555-0142', 'spravato', 'new', 'joy@uptms.test', 42, 'sarah.m@example.com', 'What does the first appointment involve, and how long should my ride wait?', 'Request a consultation'],
        ['Noah Bennett', '(253) 555-7731', 'spravato', 'closed', 'cathy@uptms.test', 9 * 1440, null, null, 'Request a consultation'],
        ['Grace Holt', '(253) 555-6620', 'insurance', 'scheduled', 'cathy@uptms.test', 12 * 1440, 'grace.h@example.com', null, 'Ask about coverage & cost'],
        ['Free Crypto Bot', '(555) 555-0100', 'insurance', 'spam', null, 5 * 1440, 'win@example.com', 'Click here to claim', null],
    ];

    public function __construct(private readonly AuditRecorder $audit) {}

    public function run(): void
    {
        if (ConsultationRequest::query()->exists()) {
            return;
        }

        $staff = User::query()->pluck('id', 'email');

        $this->audit->withoutAuditing(function () use ($staff): void {
            foreach (self::REQUESTS as [$name, $phone, $topic, $status, $assignee, $minutesAgo, $email, $message, $cta]) {
                $at = Carbon::now()->subMinutes($minutesAgo);
                $factory = ConsultationRequest::factory()->status(ConsultationStatus::from($status))->receivedAt($at);
                $assigneeId = $assignee === null ? null : $staff->get($assignee);
                $factory = is_int($assigneeId) ? $factory->assignedTo($assigneeId) : $factory;

                $request = $factory->create([
                    'name' => $name,
                    'phone' => $phone,
                    'email' => $email,
                    'message' => $message,
                    'topic' => $topic,
                    'best_time' => $topic === 'eligibility' ? BestTime::Morning->value : BestTime::NoPreference->value,
                    'cta' => $cta,
                    'referrer_host' => $minutesAgo % 2 === 0 ? 'google.com' : null,
                ]);

                if ($status !== 'new') {
                    $request->forceFill(['contacted_at' => $at->copy()->addHours(3)])->saveQuietly();
                }
            }
        });

        $daniel = ConsultationRequest::query()->where('topic', 'eligibility')->first();
        $joy = $staff->get('joy@uptms.test');
        $cathy = $staff->get('cathy@uptms.test');

        if ($daniel instanceof ConsultationRequest && is_int($joy) && is_int($cathy)) {
            foreach ([[$cathy, 'Premera usually needs prior auth. Ask for previous medication list.', 1440 + 60], [$joy, 'Called — no answer. Sent follow-up email with the first-visit checklist.', 1440 - 20]] as [$author, $body, $minutesAgo]) {
                $note = new ConsultationNote(['body' => $body]);
                $note->forceFill(['consultation_request_id' => $daniel->id, 'user_id' => $author, 'created_at' => now()->subMinutes($minutesAgo)])->save();
            }
        }
    }
}
