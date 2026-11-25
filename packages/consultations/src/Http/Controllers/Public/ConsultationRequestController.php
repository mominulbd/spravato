<?php

declare(strict_types=1);

namespace Uptms\Consultations\Http\Controllers\Public;

use App\Support\Http\ApiResponse;
use App\Support\Http\SiteClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Uptms\Consultations\Actions\SubmitConsultationRequest;
use Uptms\Consultations\Http\Requests\SubmitConsultationRequestRequest;
use Uptms\Consultations\Services\Turnstile;

class ConsultationRequestController
{
    /**
     * `POST /public/consultation-requests`. Answers with the reference only;
     * nothing the person typed is echoed back.
     */
    public function store(SubmitConsultationRequestRequest $request, SubmitConsultationRequest $action, Turnstile $turnstile): JsonResponse
    {
        // A filled honeypot gets the same answer as a person, so bots learn nothing.
        if ($request->isBot()) {
            return ApiResponse::created(['reference' => null], 'Request received.');
        }

        if (! $turnstile->verify($request->string('turnstile_token')->toString() ?: null, SiteClient::ip($request))) {
            throw ValidationException::withMessages([
                'turnstile_token' => 'We could not confirm you are not a robot. Please try again, or call the clinic.',
            ]);
        }

        /** @phpstan-ignore argument.type (validated shape) */
        $consultation = $action->execute($request->validated(), SiteClient::userAgent($request));

        return ApiResponse::created(['reference' => $consultation->reference], 'Request received.');
    }
}
