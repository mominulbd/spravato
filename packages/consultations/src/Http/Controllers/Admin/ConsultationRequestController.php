<?php

declare(strict_types=1);

namespace Uptms\Consultations\Http\Controllers\Admin;

use App\Support\Http\ApiResponse;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Uptms\AuditLog\Services\AuditRecorder;
use Uptms\Consultations\Actions\AddConsultationNote;
use Uptms\Consultations\Actions\AssignConsultationRequest;
use Uptms\Consultations\Actions\DeleteConsultationRequest;
use Uptms\Consultations\Actions\RecordConsultationView;
use Uptms\Consultations\Actions\RescheduleFollowUp;
use Uptms\Consultations\Actions\TransitionConsultationStatus;
use Uptms\Consultations\Domain\Enums\BestTime;
use Uptms\Consultations\Domain\Enums\ConsultationStatus;
use Uptms\Consultations\Domain\Enums\ConsultationTopic;
use Uptms\Consultations\Http\Requests\UpdateConsultationRequestRequest;
use Uptms\Consultations\Models\ConsultationRequest;
use Uptms\Consultations\Services\BlindIndex;
use Uptms\Consultations\Services\ConsultationExporter;
use Uptms\Consultations\Services\ConsultationOverview;
use Uptms\Consultations\Services\ConsultationPresenter;
use Uptms\Permissions\Contracts\HasPermissions;
use Uptms\Users\Services\StaffDirectory;

/**
 * Figma 04 (inbox) and 05 (request detail). Every query is scoped to what
 * the caller may see; `{consultation}` is bound within that scope (see the
 * service provider), so a request outside it is NOT_FOUND.
 */
class ConsultationRequestController
{
    private const array SORTS = ['received_at' => 'created_at', 'follow_up_at' => 'follow_up_at'];

    public function __construct(private readonly ConsultationPresenter $presenter) {}

    /** `?search=&filter[status]&filter[topic]&filter[assigned_to]=me|unassigned|<id>&filter[received]=7|30|90&sort=-received_at&per_page` */
    public function index(Request $request, BlindIndex $index, StaffDirectory $staff): JsonResponse
    {
        $actor = $this->actor($request);
        Gate::authorize('viewAny', ConsultationRequest::class);

        $validated = $request->validate([
            'search' => ['sometimes', 'nullable', 'string', 'max:100'],
            'filter.status' => ['sometimes', 'nullable', Rule::in([...array_column(ConsultationStatus::cases(), 'value'), 'overdue'])],
            'filter.topic' => ['sometimes', 'nullable', Rule::enum(ConsultationTopic::class)],
            'filter.assigned_to' => ['sometimes', 'nullable', 'regex:/^(me|unassigned|\d+)$/'],
            'filter.received' => ['sometimes', 'nullable', Rule::in(['7', '30', '90'])],
            'sort' => ['sometimes', Rule::in([...array_keys(self::SORTS), ...array_map(fn (string $s): string => '-'.$s, array_keys(self::SORTS))])],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $filter = $validated['filter'] ?? [];
        $sort = $validated['sort'] ?? '-received_at';
        $search = trim((string) ($validated['search'] ?? ''));

        $query = ConsultationRequest::query()
            ->visibleTo($actor)
            ->when($search !== '', function (Builder $query) use ($search, $index): void {
                $tokens = $index->query($search);
                $query->where(function (Builder $where) use ($search, $tokens): void {
                    $where->where('reference', 'like', '%'.str_replace(['%', '_'], ['\%', '\_'], mb_strtoupper($search)).'%');

                    if ($tokens !== []) {
                        $where->orWhere(function (Builder $all) use ($tokens): void {
                            foreach ($tokens as $token) {
                                $all->where('search_index', 'like', '%'.$token.'%');
                            }
                        });
                    }
                });
            })
            ->when(($filter['status'] ?? null) === 'overdue', fn (Builder $q) => $q->where('status', ConsultationStatus::New)->where('follow_up_at', '<', now()))
            ->when(isset($filter['status']) && $filter['status'] !== 'overdue', fn (Builder $q) => $q->where('status', $filter['status']))
            ->when(isset($filter['topic']), fn (Builder $q) => $q->where('topic', $filter['topic']))
            ->when(isset($filter['assigned_to']), fn (Builder $q) => match ($filter['assigned_to']) {
                'me' => $q->where('assigned_to', $actor->getAuthIdentifier()),
                'unassigned' => $q->whereNull('assigned_to'),
                default => $q->where('assigned_to', (int) $filter['assigned_to']),
            })
            ->when(isset($filter['received']), fn (Builder $q) => $q->where('created_at', '>=', now()->subDays((int) $filter['received'])));

        $column = self::SORTS[ltrim($sort, '-')];
        $page = $query
            ->orderBy($column, str_starts_with($sort, '-') ? 'desc' : 'asc')
            ->orderBy('id', str_starts_with($sort, '-') ? 'desc' : 'asc')
            ->paginate((int) ($validated['per_page'] ?? 10))
            ->withQueryString();

        $counts = ConsultationRequest::query()->visibleTo($actor)
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->map(fn (mixed $count): int => (int) $count);

        return new JsonResponse([
            'success' => true,
            'message' => 'Requests retrieved.',
            'data' => $this->presenter->list($page->items()),
            'meta' => [
                'current_page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'last_page' => $page->lastPage(),
                'from' => $page->firstItem(),
                'to' => $page->lastItem(),
                'counts' => [
                    'all' => $counts->sum(),
                    ...array_combine(
                        array_column(ConsultationStatus::cases(), 'value'),
                        array_map(fn (ConsultationStatus $status): int => $counts->get($status->value, 0), ConsultationStatus::cases()),
                    ),
                ],
                'topics' => array_map(fn (ConsultationTopic $topic): array => ['value' => $topic->value, 'label' => $topic->shortLabel()], ConsultationTopic::cases()),
                'best_times' => BestTime::options(),
                'assignees' => $actor->hasPermission('consultations.view_all') ? $staff->withPermission('consultations.view') : [],
                'can' => [
                    'update' => $actor->hasPermission('consultations.update'),
                    'assign' => Gate::allows('assign', ConsultationRequest::class),
                    'export' => Gate::allows('export', ConsultationRequest::class),
                    'delete' => $actor->hasPermission('consultations.export'),
                ],
            ],
            'links' => [
                'first' => $page->url(1),
                'prev' => $page->previousPageUrl(),
                'next' => $page->nextPageUrl(),
                'last' => $page->url($page->lastPage()),
            ],
        ]);
    }

    /** Opening a request decrypts it for the viewer; the view is audited. */
    public function show(ConsultationRequest $consultation, RecordConsultationView $views): JsonResponse
    {
        Gate::authorize('view', $consultation);
        $views->execute($consultation);

        return ApiResponse::success($this->presenter->detail($consultation), 'Request retrieved.');
    }

    public function update(
        UpdateConsultationRequestRequest $request,
        ConsultationRequest $consultation,
        TransitionConsultationStatus $transition,
        AssignConsultationRequest $assign,
        RescheduleFollowUp $reschedule,
    ): JsonResponse {
        $model = $consultation;
        $actor = $this->actor($request);
        $validated = $request->validated();

        DB::transaction(function () use ($model, $validated, $actor, $transition, $assign, $reschedule): void {
            if (array_key_exists('assigned_to', $validated)) {
                $assign->execute($model, $validated['assigned_to'] === null ? null : (int) $validated['assigned_to']);
            }

            if (isset($validated['status']) && $validated['status'] !== $model->status->value) {
                $transition->execute($model, ConsultationStatus::from($validated['status']), $actor);
            }

            if (isset($validated['follow_up_at'])) {
                $reschedule->execute($model, Carbon::parse($validated['follow_up_at']));
            }
        });

        return ApiResponse::success($this->presenter->detail($model->refresh()), 'Changes saved.');
    }

    public function note(Request $request, ConsultationRequest $consultation, AddConsultationNote $action): JsonResponse
    {
        $model = $consultation;
        Gate::authorize('update', $model);
        $validated = $request->validate(['body' => ['required', 'string', 'max:1000']], [
            'body.required' => 'Write a note before saving.',
            'body.max' => 'Keep notes under 1,000 characters.',
        ]);
        $action->execute($model, $validated['body'], $this->actor($request));

        return ApiResponse::created($this->presenter->detail($model->refresh()), 'Note saved.');
    }

    public function destroy(ConsultationRequest $consultation, DeleteConsultationRequest $action): JsonResponse
    {
        Gate::authorize('delete', $consultation);
        $action->execute($consultation);

        return ApiResponse::success(null, 'Request deleted.');
    }

    /** Figma 04's selection bar: assign, mark contacted, mark as spam. Requests that can't move are skipped. */
    public function bulk(Request $request, TransitionConsultationStatus $transition, AssignConsultationRequest $assign): JsonResponse
    {
        $actor = $this->actor($request);
        $validated = $request->validate([
            'action' => ['required', Rule::in(['assign', 'contacted', 'spam'])],
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['integer', 'distinct'],
            'assigned_to' => ['required_if:action,assign', 'nullable', 'integer'],
        ]);

        if ($validated['action'] === 'assign') {
            Gate::authorize('assign', ConsultationRequest::class);
        }

        $models = ConsultationRequest::query()->visibleTo($actor)->whereIn('id', $validated['ids'])->get();
        $done = 0;

        foreach ($models as $model) {
            if (! Gate::allows('update', $model)) {
                continue;
            }

            if ($validated['action'] === 'assign') {
                $assign->execute($model, $validated['assigned_to'] === null ? null : (int) $validated['assigned_to']);
                $done++;

                continue;
            }

            $to = $validated['action'] === 'spam' ? ConsultationStatus::Spam : ConsultationStatus::Contacted;

            if ($model->status->canTransitionTo($to)) {
                $transition->execute($model, $to, $actor);
                $done++;
            }
        }

        $skipped = count($validated['ids']) - $done;

        return ApiResponse::success(['updated' => $done, 'skipped' => $skipped], $done === 1 ? '1 request updated.' : $done.' requests updated.');
    }

    public function export(Request $request, ConsultationExporter $exporter, AuditRecorder $audit): StreamedResponse
    {
        $actor = $this->actor($request);
        Gate::authorize('export', ConsultationRequest::class);
        $validated = $request->validate([
            'filter.status' => ['sometimes', 'nullable', Rule::enum(ConsultationStatus::class)],
            'filter.topic' => ['sometimes', 'nullable', Rule::enum(ConsultationTopic::class)],
            'filter.received' => ['sometimes', 'nullable', Rule::in(['7', '30', '90'])],
        ]);
        $filter = $validated['filter'] ?? [];

        $query = ConsultationRequest::query()->visibleTo($actor)
            ->when(isset($filter['status']), fn (Builder $q) => $q->where('status', $filter['status']))
            ->when(isset($filter['topic']), fn (Builder $q) => $q->where('topic', $filter['topic']))
            ->when(isset($filter['received']), fn (Builder $q) => $q->where('created_at', '>=', now()->subDays((int) $filter['received'])));

        $audit->record('consultation.exported', null, null, ['filters' => $filter, 'rows' => min((clone $query)->count(), ConsultationExporter::MAX_ROWS)], label: 'Consultation requests');

        return $exporter->stream($query, 'consultation-requests-'.now()->format('Y-m-d').'.csv');
    }

    /** Figma 03: `?range=7|30|90`. */
    public function overview(Request $request, ConsultationOverview $overview): JsonResponse
    {
        Gate::authorize('viewAny', ConsultationRequest::class);
        $validated = $request->validate(['range' => ['sometimes', Rule::in(['7', '30', '90'])]]);

        return ApiResponse::success($overview->for($this->actor($request), (int) ($validated['range'] ?? 30)), 'Overview retrieved.');
    }

    /** The sidebar badge: new requests waiting for a call back. */
    public function waiting(Request $request, ConsultationOverview $overview): JsonResponse
    {
        Gate::authorize('viewAny', ConsultationRequest::class);

        return ApiResponse::success(['waiting' => $overview->waiting($this->actor($request))]);
    }

    private function actor(Request $request): HasPermissions&Authenticatable
    {
        $user = $request->user();
        assert($user instanceof HasPermissions);

        return $user;
    }
}
