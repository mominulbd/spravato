<?php

declare(strict_types=1);

namespace Uptms\Consultations\Console\Commands;

use Illuminate\Console\Command;
use Uptms\AuditLog\Services\AuditRecorder;
use Uptms\Consultations\Actions\DeleteConsultationRequest;
use Uptms\Consultations\Domain\Enums\ConsultationStatus;
use Uptms\Consultations\Models\ConsultationRequest;
use Uptms\Settings\Services\Settings;

/**
 * Retention (Clinic settings → Privacy): requests older than the retention
 * period are deleted for good, spam after 30 days. Runs daily.
 */
final class PurgeConsultationRequestsCommand extends Command
{
    protected $signature = 'consultations:purge';

    protected $description = 'Delete consultation requests past the retention period';

    public function handle(Settings $settings, DeleteConsultationRequest $delete, AuditRecorder $audit): int
    {
        $retention = max(30, (int) $settings->get('privacy.consultation_retention_days'));
        $spam = (int) config('consultations.spam_retention_days');
        $count = 0;

        ConsultationRequest::query()
            ->where(fn ($q) => $q
                ->where('created_at', '<', now()->subDays($retention))
                ->orWhere(fn ($q) => $q->where('status', ConsultationStatus::Spam)->where('created_at', '<', now()->subDays($spam))))
            ->chunkById(200, function ($requests) use ($delete, &$count): void {
                foreach ($requests as $request) {
                    $delete->execute($request, 'purged');
                    $count++;
                }
            });

        if ($count > 0) {
            $audit->record('consultation.retention_run', null, null, ['deleted' => $count, 'retention_days' => $retention], label: 'Consultation requests');
        }

        $this->info($count === 1 ? 'Deleted 1 request.' : "Deleted {$count} requests.");

        return self::SUCCESS;
    }
}
