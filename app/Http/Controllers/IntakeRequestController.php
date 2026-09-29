<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Draw;
use App\Models\IntakeRequest;
use App\Models\IntakeRequestEvent;
use App\Services\CustomerMessengerService;
use App\Services\NumberLimitService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Validation\Rule;

class IntakeRequestController extends Controller
{
    public function index(HttpRequest $request): View
    {
        $this->authorize('viewAny', IntakeRequest::class);

        $user = auth()->user();
        $branchIds = $user?->visibleBranchIds() ?? [];
        $query = IntakeRequest::query()
            ->with(['branch', 'draw', 'customer', 'incomingMessage'])
            ->orderByDesc('created_at');

        if ($user?->canViewAllBranchesForRead()) {
            if ($user?->organization_id) {
                $query->where('organization_id', $user->organization_id);
            } else {
                $query->whereRaw('1 = 0');
            }
        } else {
            $query->whereIn('branch_id', $branchIds);
        }

        $filters = $request->validate([
            'status' => ['nullable', Rule::in([
                IntakeRequest::STATUS_PENDING,
                IntakeRequest::STATUS_NEEDS_REVIEW,
                IntakeRequest::STATUS_CONFIRMED,
                IntakeRequest::STATUS_REJECTED,
            ])],
            'branch_id' => ['nullable', 'integer', Rule::in($branchIds ?: [-1])],
            'draw_id' => [
                'nullable',
                'integer',
                Rule::in(
                    Draw::query()
                        ->when($user?->organization_id, fn ($drawQuery) => $drawQuery->where('organization_id', $user->organization_id), fn ($drawQuery) => $drawQuery->whereRaw('1 = 0'))
                        ->pluck('id')
                        ->all() ?: [-1]
                ),
            ],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'customer_phone' => ['nullable', 'string', 'max:255'],
            'detected_number' => ['nullable', 'string', 'max:2'],
        ]);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['branch_id'])) {
            $query->where('branch_id', $filters['branch_id']);
        }

        if (! empty($filters['draw_id'])) {
            $query->where('draw_id', $filters['draw_id']);
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        if (! empty($filters['customer_phone'])) {
            $query->whereHas('customer', function ($customerQuery) use ($filters): void {
                $customerQuery->where('phone', 'like', '%' . $filters['customer_phone'] . '%');
            });
        }

        if (! empty($filters['detected_number'])) {
            $query->where('detected_number', str_pad($filters['detected_number'], 2, '0', STR_PAD_LEFT));
        }

        return view('requests.index', [
            'requests' => $query->paginate(25)->withQueryString(),
            'branches' => Branch::query()->whereIn('id', $branchIds)->orderBy('name')->get(),
            'draws' => Draw::query()
                ->when($user?->organization_id, fn ($drawQuery) => $drawQuery->where('organization_id', $user->organization_id), fn ($drawQuery) => $drawQuery->whereRaw('1 = 0'))
                ->orderBy('draw_time')
                ->get(),
            'filters' => $filters,
            'staleThresholdHours' => 24,
        ]);
    }

    public function show(IntakeRequest $intakeRequest): View
    {
        $this->authorize('view', $intakeRequest);

        return view('requests.show', [
            'request' => $intakeRequest->load(['branch', 'draw', 'customer', 'incomingMessage.response', 'events.user', 'outgoingMessages.user']),
            'canMessageCustomer' => app(CustomerMessengerService::class)->canMessageCustomer($intakeRequest),
        ]);
    }

    public function edit(IntakeRequest $intakeRequest): View
    {
        $this->authorize('update', $intakeRequest);

        $user = auth()->user();

        return view('requests.edit', [
            'request' => $intakeRequest->load(['branch', 'draw', 'customer', 'incomingMessage.response']),
            'draws' => $user?->organization_id
                ? Draw::query()
                    ->where('organization_id', $user->organization_id)
                    ->orderBy('draw_time')
                    ->get()
                : collect(),
        ]);
    }

    public function update(HttpRequest $request, IntakeRequest $intakeRequest): RedirectResponse
    {
        $this->authorize('update', $intakeRequest);

        $originalValues = [
            'status' => $intakeRequest->status,
            'detected_number' => $intakeRequest->detected_number,
            'detected_amount' => $intakeRequest->detected_amount,
            'reventado_amount' => $intakeRequest->reventado_amount,
            'draw_id' => $intakeRequest->draw_id,
            'notes' => $intakeRequest->notes,
        ];

        $validated = $request->validate([
            'detected_number' => ['nullable', 'regex:/^(0[0-9]|[1-9][0-9])$/'],
            'detected_amount' => ['nullable', 'numeric', 'gt:0'],
            'reventado_amount' => ['nullable', 'numeric', 'gt:0'],
            'draw_id' => [
                'nullable',
                'integer',
                Rule::exists('draws', 'id')->where(function ($query): void {
                    $query->where('organization_id', auth()->user()?->organization_id)
                        ->whereIn('status', [Draw::STATUS_ACTIVE, Draw::STATUS_INACTIVE]);
                }),
            ],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $intakeRequest->update($validated);

        $evaluation = $this->limitEvaluationForRequest($intakeRequest);
        $finalValues = $validated;
        $statusChanged = false;

        if ($evaluation['final_status'] !== $intakeRequest->status) {
            $finalValues['status'] = $evaluation['final_status'];
            $statusChanged = true;
        }

        if ($evaluation['final_status'] === IntakeRequest::STATUS_NEEDS_REVIEW && $evaluation['note'] !== null) {
            if (in_array($evaluation['reason'], ['blocked', 'manual_review'], true)) {
                $finalValues['notes'] = $evaluation['note'];
            } elseif ($evaluation['reason'] === 'over_limit') {
                $finalValues['notes'] = trim(implode(' ', array_filter([
                    $validated['notes'] ?? null,
                    $evaluation['note'],
                ])));
            }
        }

        if ($finalValues !== $validated) {
            $intakeRequest->update($finalValues);
        }

        $intakeRequest->events()->create([
            'user_id' => auth()->id(),
            'event_type' => IntakeRequestEvent::EVENT_EDITED,
            'old_values' => $originalValues,
            'new_values' => $finalValues,
            'notes' => 'Manual edit from request detail page.',
            'created_at' => now(),
        ]);

        if ($statusChanged) {
            $intakeRequest->events()->create([
                'user_id' => auth()->id(),
                'event_type' => IntakeRequestEvent::EVENT_STATUS_CHANGED,
                'old_values' => ['status' => $originalValues['status']],
                'new_values' => ['status' => $intakeRequest->status],
                'notes' => $evaluation['note'] ?? 'Request re-evaluated after edit.',
                'created_at' => now(),
            ]);
        }

        return redirect()
            ->route('intake-requests.index')
            ->with('status', __('Request updated successfully.'));
    }

    public function confirm(IntakeRequest $intakeRequest, CustomerMessengerService $customerMessengerService): RedirectResponse
    {
        $this->authorize('confirm', $intakeRequest);

        $previousStatus = $intakeRequest->status;
        $evaluation = $this->limitEvaluationForRequest($intakeRequest);

        $missingFields = $intakeRequest->missingConfirmationFields();

        if ($missingFields !== []) {
            $note = $missingFields === ['draw']
                ? 'Draw schedule is required. Manual review required.'
                : __('Missing :fields. Complete the request (ask the customer if needed) before confirming.', [
                    'fields' => implode(', ', array_map(fn (string $field) => __($field), $missingFields)),
                ]);

            $intakeRequest->update([
                'status' => IntakeRequest::STATUS_NEEDS_REVIEW,
                'confirmed_by' => null,
                'confirmed_at' => null,
                'rejected_by' => null,
                'rejected_at' => null,
                'notes' => $note,
            ]);

            $intakeRequest->events()->create([
                'user_id' => auth()->id(),
                'event_type' => IntakeRequestEvent::EVENT_STATUS_CHANGED,
                'old_values' => ['status' => $previousStatus],
                'new_values' => ['status' => IntakeRequest::STATUS_NEEDS_REVIEW],
                'notes' => $note,
                'created_at' => now(),
            ]);

            return redirect()
                ->route('intake-requests.edit', $intakeRequest)
                ->with('status', __('Cannot confirm yet: :fields required.', ['fields' => implode(', ', array_map(fn (string $field) => __($field), $missingFields))]));
        }

        if ($evaluation['final_status'] === IntakeRequest::STATUS_NEEDS_REVIEW && $evaluation['note'] !== null) {
            $intakeRequest->update([
                'status' => IntakeRequest::STATUS_NEEDS_REVIEW,
                'confirmed_by' => null,
                'confirmed_at' => null,
                'rejected_by' => null,
                'rejected_at' => null,
                'notes' => trim($evaluation['note']),
            ]);

            $intakeRequest->events()->create([
                'user_id' => auth()->id(),
                'event_type' => IntakeRequestEvent::EVENT_STATUS_CHANGED,
                'old_values' => ['status' => $previousStatus],
                'new_values' => ['status' => IntakeRequest::STATUS_NEEDS_REVIEW],
                'notes' => $evaluation['note'],
                'created_at' => now(),
            ]);

            return redirect()
                ->route('intake-requests.index')
                ->with('status', __($evaluation['note']));
        }

        $intakeRequest->update([
            'status' => IntakeRequest::STATUS_CONFIRMED,
            'confirmed_by' => auth()->id(),
            'confirmed_at' => now(),
            'rejected_by' => null,
            'rejected_at' => null,
            'awaiting_reply_since' => null,
        ]);

        $intakeRequest->events()->create([
            'user_id' => auth()->id(),
            'event_type' => IntakeRequestEvent::EVENT_CONFIRMED,
            'old_values' => ['status' => $previousStatus],
            'new_values' => ['status' => IntakeRequest::STATUS_CONFIRMED],
            'notes' => 'Request confirmed.',
            'created_at' => now(),
        ]);

        $customerMessengerService->notifyConfirmed($intakeRequest, auth()->user());

        return redirect()
            ->route('intake-requests.index')
            ->with('status', __('Request confirmed.'));
    }

    public function reject(HttpRequest $request, IntakeRequest $intakeRequest, CustomerMessengerService $customerMessengerService): RedirectResponse
    {
        $this->authorize('reject', $intakeRequest);

        $previousStatus = $intakeRequest->status;
        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:5000'],
            'notify_customer' => ['nullable', 'boolean'],
        ]);

        $intakeRequest->update([
            'status' => IntakeRequest::STATUS_REJECTED,
            'rejected_by' => auth()->id(),
            'rejected_at' => now(),
            'notes' => $validated['rejection_reason'],
            'confirmed_by' => null,
            'confirmed_at' => null,
            'awaiting_reply_since' => null,
        ]);

        $intakeRequest->events()->create([
            'user_id' => auth()->id(),
            'event_type' => IntakeRequestEvent::EVENT_REJECTED,
            'old_values' => ['status' => $previousStatus],
            'new_values' => ['status' => IntakeRequest::STATUS_REJECTED],
            'notes' => $validated['rejection_reason'],
            'created_at' => now(),
        ]);

        // The reason is shown to the customer unless the operator unticks "notify customer".
        if ($request->boolean('notify_customer', true)) {
            $customerMessengerService->notifyRejected($intakeRequest, auth()->user(), $validated['rejection_reason']);
        }

        return redirect()
            ->route('intake-requests.index')
            ->with('status', __('Request rejected.'));
    }

    public function clarify(HttpRequest $request, IntakeRequest $intakeRequest, CustomerMessengerService $customerMessengerService): RedirectResponse
    {
        $this->authorize('update', $intakeRequest);

        $validated = $request->validate([
            'question' => ['required', 'string', 'max:1000'],
        ]);

        if (! $customerMessengerService->canMessageCustomer($intakeRequest)) {
            return redirect()
                ->route('intake-requests.show', $intakeRequest)
                ->withErrors(['question' => __('This request has no customer chat to reply to (it was created manually).')]);
        }

        $outgoingMessage = $customerMessengerService->requestClarification($intakeRequest, auth()->user(), $validated['question']);

        $intakeRequest->update([
            'status' => IntakeRequest::STATUS_NEEDS_REVIEW,
            'awaiting_reply_since' => now(),
        ]);

        $intakeRequest->events()->create([
            'user_id' => auth()->id(),
            'event_type' => IntakeRequestEvent::EVENT_CLARIFICATION_REQUESTED,
            'old_values' => null,
            'new_values' => ['outgoing_message_id' => $outgoingMessage?->id],
            'notes' => $validated['question'],
            'created_at' => now(),
        ]);

        return redirect()
            ->route('intake-requests.show', $intakeRequest)
            ->with('status', __('Question sent to the customer. Their next reply will be attached to this request.'));
    }

    /**
     * @return array{
     *     limit: ?\App\Models\NumberLimit,
     *     is_blocked: bool,
     *     requires_manual_review: bool,
     *     is_over_limit: bool,
     *     final_status: string,
     *     note: ?string,
     *     reason: ?string,
     *     customer_review_notice: ?string
     * }
     */
    private function limitEvaluationForRequest(IntakeRequest $intakeRequest): array
    {
        if ($intakeRequest->draw_id === null || $intakeRequest->detected_number === null || $intakeRequest->detected_amount === null) {
            return [
                'limit' => null,
                'is_blocked' => false,
                'requires_manual_review' => false,
                'is_over_limit' => false,
                'final_status' => $intakeRequest->status,
                'note' => null,
                'reason' => null,
                'customer_review_notice' => null,
            ];
        }

        $draw = Draw::query()->whereKey($intakeRequest->draw_id)->first();

        if (! $draw) {
            return [
                'limit' => null,
                'is_blocked' => false,
                'requires_manual_review' => false,
                'is_over_limit' => false,
                'final_status' => $intakeRequest->status,
                'note' => null,
                'reason' => null,
                'customer_review_notice' => null,
            ];
        }

        return app(NumberLimitService::class)->evaluateRequestForAmount(
            $intakeRequest->organization,
            $intakeRequest->branch,
            $draw,
            $intakeRequest->detected_number,
            (float) $intakeRequest->detected_amount,
            $intakeRequest->draw_date?->toDateString(),
            $intakeRequest->id,
        );
    }
}
