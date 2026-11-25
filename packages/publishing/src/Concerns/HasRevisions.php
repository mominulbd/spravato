<?php

declare(strict_types=1);

namespace Uptms\Publishing\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Auth;
use Uptms\Publishing\Models\Revision;

/**
 * For models whose website version is a snapshot: `published_revision_id`
 * points at the revision the public API serves, so staff can keep editing
 * without touching the live page.
 *
 * @mixin Model
 */
trait HasRevisions
{
    /** @return MorphMany<Revision, $this> */
    public function revisions(): MorphMany
    {
        return $this->morphMany(Revision::class, 'revisionable')->latest('id');
    }

    /** @return BelongsTo<Revision, $this> */
    public function publishedRevision(): BelongsTo
    {
        return $this->belongsTo(Revision::class, 'published_revision_id');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function recordRevision(string $kind, array $payload, ?string $note = null): Revision
    {
        $id = Auth::guard('web')->id();

        /** @var Revision */
        return $this->revisions()->create([
            'kind' => $kind,
            'payload' => $payload,
            'note' => $note,
            'user_id' => is_int($id) ? $id : null,
        ]);
    }
}
