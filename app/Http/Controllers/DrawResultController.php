<?php

namespace App\Http\Controllers;

use App\Models\Draw;
use App\Models\DrawResult;
use App\Services\DrawResultService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DrawResultController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', DrawResult::class);

        $validated = $request->validate([
            'draw_date' => ['nullable', 'date_format:Y-m-d'],
        ]);
        $drawDate = $validated['draw_date'] ?? now()->toDateString();
        $organizationId = $request->user()->organization_id;

        $draws = Draw::query()
            ->where('organization_id', $organizationId)
            ->where('status', Draw::STATUS_ACTIVE)
            ->orderBy('draw_time')
            ->get();

        $results = DrawResult::query()
            ->with('enteredByUser')
            ->withCount('payouts')
            ->withSum('payouts', 'total_prize')
            ->where('organization_id', $organizationId)
            ->whereDate('draw_date', $drawDate)
            ->get()
            ->keyBy('draw_id');

        return view('results.index', [
            'draws' => $draws,
            'results' => $results,
            'drawDate' => $drawDate,
        ]);
    }

    public function store(Request $request, DrawResultService $drawResultService): RedirectResponse
    {
        $this->authorize('create', DrawResult::class);

        $validated = $request->validate([
            'draw_id' => ['required', 'integer', Rule::exists('draws', 'id')->where('organization_id', $request->user()->organization_id)],
            'draw_date' => ['required', 'date_format:Y-m-d'],
            'winning_number' => ['required', 'regex:/^[0-9]{2}$/'],
            'reventado_hit' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $draw = Draw::query()->findOrFail($validated['draw_id']);
        $drawnAt = Carbon::parse($validated['draw_date'] . ' ' . $draw->draw_time, $draw->timezone ?: config('app.timezone'));

        if (now()->lessThan($drawnAt)) {
            throw ValidationException::withMessages([
                'winning_number' => __('This draw has not taken place yet.'),
            ]);
        }

        $result = $drawResultService->record(
            draw: $draw,
            drawDate: $validated['draw_date'],
            winningNumber: $validated['winning_number'],
            reventadoHit: $request->boolean('reventado_hit'),
            user: $request->user(),
            notes: $validated['notes'] ?? null,
        );

        return redirect()
            ->route('results.index', ['draw_date' => $validated['draw_date']])
            ->with('status', __('Result :number saved for :draw. Winners: :count.', [
                'number' => $result->winning_number,
                'draw' => $draw->name,
                'count' => $result->payouts->count(),
            ]));
    }

    public function notify(Request $request, DrawResult $result, DrawResultService $drawResultService): RedirectResponse
    {
        $this->authorize('notify', $result);

        $notified = $drawResultService->notifyWinners($result, $request->user());

        return redirect()
            ->route('results.index', ['draw_date' => $result->draw_date->toDateString()])
            ->with('status', __('Winners notified: :count. The result is now locked.', ['count' => $notified]));
    }
}
