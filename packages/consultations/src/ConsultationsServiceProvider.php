<?php

declare(strict_types=1);

namespace Uptms\Consultations;

use App\Support\Packages\ModuleServiceProvider;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Uptms\Consultations\Events\ConsultationRequestSubmitted;
use Uptms\Consultations\Listeners\SendPatientConfirmation;
use Uptms\Consultations\Listeners\SendStaffAlert;
use Uptms\Consultations\Models\ConsultationRequest;
use Uptms\Consultations\Policies\ConsultationRequestPolicy;
use Uptms\Consultations\Services\BlindIndex;
use Uptms\Consultations\Services\ConsultationPresenter;
use Uptms\Consultations\Sources\ConsultationFormSource;
use Uptms\Permissions\Contracts\HasPermissions;
use Uptms\Publishing\Services\BlockSources;

final class ConsultationsServiceProvider extends ModuleServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $singletons = [
        BlindIndex::class => BlindIndex::class,
        ConsultationPresenter::class => ConsultationPresenter::class,
    ];

    protected array $morphMap = [
        'consultation_request' => ConsultationRequest::class,
    ];

    protected array $policies = [
        ConsultationRequest::class => ConsultationRequestPolicy::class,
    ];

    protected array $listen = [
        ConsultationRequestSubmitted::class => [SendStaffAlert::class, SendPatientConfirmation::class],
    ];

    protected function bootModule(): void
    {
        // Fourth gate: `{consultation}` only resolves within what the signed-in
        // person may see, so anything else is NOT_FOUND before a controller runs.
        Route::bind('consultation', function (string $value): ConsultationRequest {
            $user = Auth::user();

            // Bindings run before route middleware: refuse here what `permission:` would.
            if ($user instanceof HasPermissions && ! $user->hasPermission('consultations.view')) {
                throw new AccessDeniedHttpException('This action is unauthorized.');
            }

            if (! $user instanceof HasPermissions || ! ctype_digit($value)) {
                throw (new ModelNotFoundException)->setModel(ConsultationRequest::class, [$value]);
            }

            return ConsultationRequest::query()->visibleTo($user)->findOrFail((int) $value);
        });

        $this->app->make(BlockSources::class)->register('consultation-form', ConsultationFormSource::class);

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command('consultations:purge')->dailyAt('03:30')->onOneServer();
        });
    }
}
