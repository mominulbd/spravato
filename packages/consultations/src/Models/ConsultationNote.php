<?php

declare(strict_types=1);

namespace Uptms\Consultations\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A staff note on a request ("Left a voicemail at 10:15"). Encrypted:
 * notes can mention the person. Scheduling and follow-up only; clinical
 * details belong in the EHR.
 *
 * @property int $id
 * @property int $consultation_request_id
 * @property int|null $user_id
 * @property string $body
 * @property Carbon $created_at
 * @property Carbon|null $updated_at
 */
class ConsultationNote extends Model
{
    protected $table = 'consultation_request_notes';

    protected $fillable = ['body'];

    protected $hidden = ['body'];

    protected function casts(): array
    {
        return [
            'body' => 'encrypted',
            'user_id' => 'integer',
        ];
    }

    /** @return BelongsTo<ConsultationRequest, $this> */
    public function request(): BelongsTo
    {
        return $this->belongsTo(ConsultationRequest::class, 'consultation_request_id');
    }
}
