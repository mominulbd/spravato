<?php

declare(strict_types=1);

namespace Uptms\Consultations\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;
use Uptms\Consultations\Domain\Enums\ConsultationStatus;
use Uptms\Consultations\Domain\Enums\ConsultationTopic;
use Uptms\Consultations\Domain\Enums\ContactMethod;
use Uptms\Consultations\Domain\Enums\PriorTreatment;
use Uptms\Consultations\Models\ConsultationRequest;
use Uptms\Consultations\Services\BlindIndex;

/**
 * @extends Factory<ConsultationRequest>
 */
class ConsultationRequestFactory extends Factory
{
    protected $model = ConsultationRequest::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'name' => fake()->firstName().' '.fake()->lastName(),
            'phone' => '(253) 555-'.fake()->numerify('####'),
            'email' => fake()->boolean(70) ? fake()->safeEmail() : null,
            'message' => fake()->boolean(60) ? 'I would like to understand the evaluation and whether my insurance covers it.' : null,
            'topic' => fake()->randomElement(ConsultationTopic::cases()),
            'insurance' => fake()->randomElement(['Premera Blue Cross', 'Regence BlueShield', 'Molina Healthcare', 'Other']),
            'prior_treatment' => fake()->randomElement(PriorTreatment::cases())->value,
            'contact_method' => fake()->randomElement(ContactMethod::cases()),
            'source_page' => '/contact',
            'device' => 'Mobile · Safari',
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (ConsultationRequest $request): void {
            $index = app(BlindIndex::class);
            $request->status ??= ConsultationStatus::New;
            $request->phone_hash = $index->phoneHash($request->phone);
            $request->search_index = $index->build($request->name, $request->phone, $request->email);
            $request->follow_up_at ??= ($request->created_at ?? now())->copy()->addDay();
        })->afterCreating(function (ConsultationRequest $request): void {
            if ($request->reference === null) {
                $request->reference = sprintf('CR-%s-%04d', $request->created_at->format('Y'), $request->id);
                $request->saveQuietly();
            }
        });
    }

    public function status(ConsultationStatus $status): static
    {
        return $this->afterMaking(function (ConsultationRequest $request) use ($status): void {
            $request->status = $status;
        });
    }

    public function assignedTo(int $userId): static
    {
        return $this->afterMaking(function (ConsultationRequest $request) use ($userId): void {
            $request->assigned_to = $userId;
        });
    }

    public function receivedAt(\DateTimeInterface $at): static
    {
        return $this->afterMaking(function (ConsultationRequest $request) use ($at): void {
            $request->created_at = Carbon::instance($at);
            $request->follow_up_at = Carbon::instance($at)->addDay();
        });
    }
}
