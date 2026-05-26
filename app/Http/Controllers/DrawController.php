<?php

namespace App\Http\Controllers;

use App\Models\Draw;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class DrawController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Draw::class);

        $user = $request->user();

        $draws = Draw::query()
            ->when(
                $user?->organization_id,
                fn ($query) => $query->where('organization_id', $user->organization_id),
                fn ($query) => $query->whereRaw('1 = 0')
            )
            ->orderBy('draw_time')
            ->get();

        return view('draws.index', [
            'draws' => $draws,
            'canEditDraws' => $user?->canViewAllBranches() ?? false,
        ]);
    }

    public function edit(Request $request, Draw $draw): View
    {
        Gate::authorize('update', $draw);

        return view('draws.edit', [
            'draw' => $draw,
        ]);
    }

    public function update(Request $request, Draw $draw): RedirectResponse
    {
        Gate::authorize('update', $draw);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'draw_time' => ['required', 'date_format:H:i:s'],
            'close_time' => ['nullable', 'date_format:H:i:s'],
            'cutoff_minutes_before' => ['nullable', 'integer', 'min:0'],
            'is_accepting_requests' => ['nullable', 'boolean'],
            'status' => ['required', Rule::in([Draw::STATUS_ACTIVE, Draw::STATUS_INACTIVE])],
        ]);

        $draw->update([
            'name' => $validated['name'],
            'draw_time' => $validated['draw_time'],
            'close_time' => $validated['close_time'] ?: null,
            'cutoff_minutes_before' => array_key_exists('cutoff_minutes_before', $validated)
                ? $validated['cutoff_minutes_before']
                : 0,
            'is_accepting_requests' => $request->boolean('is_accepting_requests'),
            'status' => $validated['status'],
        ]);

        return redirect()
            ->route('draws.index')
            ->with('status', 'Draw updated successfully.');
    }
}
