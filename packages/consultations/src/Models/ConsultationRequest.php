<?php

declare(strict_types=1);

namespace Uptms\Consultations\Models;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Uptms\Consultations\Domain\Enums\BestTime;
use Uptms\Consultations\Domain\Enums\ConsultationOrigin;
use Uptms\Consultations\Domain\Enums\ConsultationStatus;
use Uptms\Consultations\Domain\Enums\ConsultationTopic;
use Uptms\Consultations\Domain\Enums\ContactMethod;
use Uptms\Consultations\Domain\Enums\PatientStatus;
use Uptms\Consultations\Factories\ConsultationRequestFactory;
use Uptms\Permissions\Contracts\HasPermissions;

/**
 * A request from the website's consultation dialog. The person's details are
 * encrypted at rest; nothing here is audited as a model diff, because the
 * audit log must never hold patient details. Actions record what happened.
 *
 * @property int $id
 * @property string|null $reference
 * @property string $name
 * @property string $phone
 * @property string|null $email
 * @property string|null $message
 * @property string|null $insurance
 * @property ContactMethod|null $contact_method
 * @property PatientStatus|null $patient_status
 * @property bool $emergency_acknowledged
 * @property ConsultationTopic $topic
 * @property ConsultationOrigin $origin
 * @property string|null $cta label of the button that opened the dialog
 * @property BestTime|null $best_time
 * @property ConsultationStatus $status
 * @property int|null $assigned_to
 * @property Carbon|null $follow_up_at
 * @property Carbon|null $first_viewed_at
 * @property Carbon|null $contacted_at
 * @property Carbon|null $scheduled_at
 * @property Carbon|null $closed_at
 * @property string|null $source_page
 * @property string|null $previous_page
 * @property string|null $referrer_host
 * @property array<string, string>|null $utm
 * @property string|null $device
 * @property string $phone_hash
 * @property string $search_index
 * @property Carbon $created_at
 * @property Carbon|null $updated_at
 */
class ConsultationRequest extends Model
{
    /** @use HasFactory<ConsultationRequestFactory> */
    use HasFactory;

    protected $fillable = [
        'name', 'phone', 'email', 'message', 'insurance', 'topic', 'best_time', 'contact_method', 'patient_status',
        'emergency_acknowledged', 'cta', 'source_page', 'previous_page', 'referrer_host', 'utm', 'device',
    ];

    /** @var array<string, mixed> */
    protected $attributes = ['origin' => 'form'];

    /** Never serialised by accident: presenters choose what leaves the API. */
    protected $hidden = ['name', 'phone', 'email', 'message', 'insurance', 'phone_hash', 'search_index'];

    protected function casts(): array
    {
        return [
            'name' => 'encrypted',
            'phone' => 'encrypted',
            'email' => 'encrypted',
            'message' => 'encrypted',
            'insurance' => 'encrypted',
            'origin' => ConsultationOrigin::class,
            'topic' => ConsultationTopic::class,
            'best_time' => BestTime::class,
            'contact_method' => ContactMethod::class,
            'patient_status' => PatientStatus::class,
            'emergency_acknowledged' => 'boolean',
            'status' => ConsultationStatus::class,
            'assigned_to' => 'integer',
            'utm' => 'array',
            'follow_up_at' => 'datetime',
            'first_viewed_at' => 'datetime',
            'contacted_at' => 'datetime',
            'scheduled_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    protected static function newFactory(): ConsultationRequestFactory
    {
        return ConsultationRequestFactory::new();
    }

    /** @return HasMany<ConsultationNote, $this> */
    public function notes(): HasMany
    {
        return $this->hasMany(ConsultationNote::class)->latest('id');
    }

    /**
     * The fourth gate: without consultations.view_all a person only sees
     * the requests assigned to them.
     *
     * @param  Builder<ConsultationRequest>  $query
     */
    public function scopeVisibleTo(Builder $query, HasPermissions&Authenticatable $actor): void
    {
        if (! $actor->hasPermission('consultations.view_all')) {
            $query->where('assigned_to', $actor->getAuthIdentifier());
        }
    }

    public function isVisibleTo(HasPermissions&Authenticatable $actor): bool
    {
        return $actor->hasPermission('consultations.view_all') || $this->assigned_to === $actor->getAuthIdentifier();
    }

    /** A new request past its follow-up time. */
    public function isOverdue(?Carbon $now = null): bool
    {
        return $this->status === ConsultationStatus::New
            && $this->follow_up_at !== null
            && $this->follow_up_at->lt($now ?? now());
    }
}
