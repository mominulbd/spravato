<?php

declare(strict_types=1);

use App\Support\Exceptions\GenericDomainException;
use App\Support\Http\ErrorCode;
use Illuminate\Support\Facades\Route;

/*
 * Every failure leaves the API in the same envelope with a stable code and
 * the request id (docs/API.md §1, §4). Test-only routes exercise each branch
 * of App\Support\Http\ExceptionRenderer.
 */
beforeEach(function (): void {
    Route::prefix('api/v1/_test')->middleware('api')->group(function (): void {
        Route::post('validate', fn () => request()->validate(['email' => ['required', 'email']]));
        Route::get('domain', fn () => throw new GenericDomainException(ErrorCode::Conflict, 'Already published.', ['field' => 'status']));
        Route::get('boom', fn () => throw new RuntimeException('SQLSTATE secret detail'));
        Route::get('private', fn () => 'never')->middleware('auth:sanctum');
    });
});

it('maps unknown API routes to NOT_FOUND', function (): void {
    assertErrorCode($this->getJson('/api/v1/does-not-exist'), 'NOT_FOUND', 404)
        ->assertJsonStructure(['success', 'message', 'error_code', 'errors', 'meta' => ['request_id']]);
});

it('maps validation failures to VALIDATION_FAILED with field errors', function (): void {
    assertErrorCode($this->postJson('/api/v1/_test/validate', ['email' => 'nope']), 'VALIDATION_FAILED', 422)
        ->assertJsonValidationErrors(['email']);
});

it('maps domain exceptions to their own code, status and context', function (): void {
    assertErrorCode($this->getJson('/api/v1/_test/domain'), 'CONFLICT', 409)
        ->assertJsonPath('message', 'Already published.')
        ->assertJsonPath('errors.field', 'status');
});

it('maps missing authentication to UNAUTHENTICATED', function (): void {
    assertErrorCode($this->getJson('/api/v1/_test/private'), 'UNAUTHENTICATED', 401);
});

it('never leaks exception details in production', function (): void {
    config(['app.debug' => false]);

    assertErrorCode($this->getJson('/api/v1/_test/boom'), 'SERVER_ERROR', 500)
        ->assertJsonMissing(['message' => 'SQLSTATE secret detail'])
        ->assertJsonPath('errors', []);
});
