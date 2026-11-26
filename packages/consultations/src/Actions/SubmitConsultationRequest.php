<?php

declare(strict_types=1);

namespace Uptms\Consultations\Actions;

use Illuminate\Support\Facades\DB;
use Uptms\AuditLog\Services\AuditRecorder;
use Uptms\Consultations\Domain\Enums\ConsultationOrigin;
use Uptms\Consultations\Domain\Enums\ConsultationStatus;
use Uptms\Consultations\Domain\Enums\ConsultationTopic;
use Uptms\Consultations\Events\ConsultationRequestSubmitted;
use Uptms\Consultations\Models\ConsultationRequest;
use Uptms\Consultations\Services\BlindIndex;
use Uptms\Consultations\Services\FollowUpClock;
use Uptms\Consultations\Services\SourceDescriber;

/**
 * Stores a request from the website's consultation dialog, sets its call-back
 * target and alerts staff (without any of the person's details).
 */
final readonly class SubmitConsultationRequest
{
    public function __construct(
        private BlindIndex $index,
        private FollowUpClock $clock,
        private SourceDescriber $describer,
        private AuditRecorder $audit,
    ) {}

    /**
     * @param  array{first_name: string, last_name: string, phone: string, email?: string|null, insurance?: string|null, contact_method?: string|null, patient_status?: string|null, topic?: string|null, best_time?: string|null, message?: string|null, emergency_acknowledged?: mixed, cta?: string|null, source_page?: string|null, referrer?: string|null, utm?: array<string, string|null>|null}  $data
     */
    public function execute(array $data, ?string $userAgent): ConsultationRequest
    {
        $phoneHash = $this->index->phoneHash($data['phone']);

        // A double submit (or a second tap after a slow network) returns the
        // first request instead of filling the inbox with copies.
        $duplicate = ConsultationRequest::query()
            ->where('phone_hash', $phoneHash)
            ->where('status', ConsultationStatus::New)
            ->where('created_at', '>=', now()->subMinutes((int) config('consultations.duplicate_window_minutes')))
            ->latest('id')
            ->first();

        if ($duplicate instanceof ConsultationRequest) {
            return $duplicate;
        }

        $siteHost = parse_url((string) config('services.frontend.url'), PHP_URL_HOST);
        $siteHost = is_string($siteHost) ? $siteHost : null;
        $referrer = $data['referrer'] ?? null;
        $utm = array_filter($data['utm'] ?? [], fn (mixed $value): bool => is_string($value) && $value !== '');

        $request = DB::transaction(function () use ($data, $userAgent, $phoneHash, $siteHost, $referrer, $utm): ConsultationRequest {
            $request = new ConsultationRequest([
                'name' => trim($data['first_name']).' '.trim($data['last_name']),
                'phone' => trim($data['phone']),
                'email' => $this->blankToNull($data['email'] ?? null),
                'insurance' => $this->blankToNull($data['insurance'] ?? null),
                'contact_method' => $this->blankToNull($data['contact_method'] ?? null),
                'patient_status' => $this->blankToNull($data['patient_status'] ?? null),
                'emergency_acknowledged' => true,
                'message' => $this->blankToNull($data['message'] ?? null),
                'topic' => $this->blankToNull($data['topic'] ?? null) ?? ConsultationTopic::Spravato->value,
                'best_time' => $this->blankToNull($data['best_time'] ?? null),
                'cta' => $this->blankToNull($data['cta'] ?? null),
                'source_page' => $this->blankToNull($data['source_page'] ?? null),
                'previous_page' => $this->describer->previousPage($referrer, $siteHost),
                'referrer_host' => $this->describer->referrerHost($referrer, $siteHost),
                'utm' => $utm === [] ? null : $utm,
                'device' => $this->describer->device($userAgent),
            ]);
            $request->status = ConsultationStatus::New;
            $request->phone_hash = $phoneHash;
            $request->search_index = $this->index->build($request->name, $request->phone, $request->email);
            $request->follow_up_at = $this->clock->dueFor(now());
            $request->origin = ConsultationOrigin::Form;
            $request->save();

            $request->reference = sprintf('CR-%s-%04d', $request->created_at->format('Y'), $request->id);
            $request->save();

            $this->audit->record('consultation.submitted', $request, null, ['source_page' => $request->source_page, 'cta' => $request->cta, 'origin' => $request->origin->value]);

            return $request;
        });

        ConsultationRequestSubmitted::dispatch($request->id);

        return $request;
    }

    private function blankToNull(?string $value): ?string
    {
        $value = $value === null ? null : trim($value);

        return $value === '' ? null : $value;
    }
}
