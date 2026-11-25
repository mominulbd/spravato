<?php

declare(strict_types=1);

namespace Uptms\AuditLog\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Context;
use Uptms\AuditLog\Models\AuditLog;

/**
 * The one writer of audit_logs. Called synchronously inside the business
 * transaction, so an action and its audit row commit or roll back together.
 */
class AuditRecorder
{
    /** @var list<string> attribute names never written to the log */
    private const array REDACTED = [
        'password', 'remember_token', 'token', 'secret',
        'two_factor_secret', 'two_factor_recovery_codes',
    ];

    private bool $suspended = false;

    /**
     * Run a callback without writing audit rows, for seeders.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function withoutAuditing(callable $callback): mixed
    {
        $previous = $this->suspended;
        $this->suspended = true;

        try {
            return $callback();
        } finally {
            $this->suspended = $previous;
        }
    }

    /**
     * @param  array<string, mixed>|null  $old
     * @param  array<string, mixed>|null  $new
     */
    public function record(
        string $action,
        ?Model $auditable = null,
        ?array $old = null,
        ?array $new = null,
        ?int $userId = null,
        ?string $label = null,
    ): ?AuditLog {
        if ($this->suspended) {
            return null;
        }

        $request = $this->request();
        $userId ??= Auth::guard('web')->id();

        return AuditLog::query()->create([
            'user_id' => is_int($userId) ? $userId : null,
            'action' => $action,
            'auditable_type' => $auditable?->getMorphClass(),
            'auditable_id' => $auditable?->getKey(),
            'auditable_label' => $label ?? $this->labelFor($auditable),
            'old_values' => $old === null ? null : $this->redact($old),
            'new_values' => $new === null ? null : $this->redact($new),
            'ip_address' => $request?->ip(),
            'user_agent' => $request !== null ? mb_substr((string) $request->userAgent(), 0, 255) : null,
            'request_id' => Context::get('request_id'),
            'created_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    public function redact(array $values): array
    {
        foreach ($values as $key => $value) {
            if (in_array($key, self::REDACTED, true)) {
                $values[$key] = '[redacted]';
            } elseif (is_array($value)) {
                /** @var array<string, mixed> $value */
                $values[$key] = $this->redact($value);
            }
        }

        return $values;
    }

    private function labelFor(?Model $model): ?string
    {
        if ($model === null) {
            return null;
        }

        foreach (['reference', 'slug', 'email', 'name'] as $attribute) {
            $value = $model->getAttribute($attribute);

            if (is_string($value) && $value !== '') {
                return mb_substr($value, 0, 255);
            }
        }

        return null;
    }

    private function request(): ?Request
    {
        return app()->runningInConsole() && ! app()->runningUnitTests() ? null : request();
    }
}
