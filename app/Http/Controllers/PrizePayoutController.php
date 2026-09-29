<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\PrizePayout;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PrizePayoutController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', PrizePayout::class);

        $branchIds = $request->user()->visibleBranchIds();
        $filters = $request->validate([
            'draw_date' => ['nullable', 'date_format:Y-m-d'],
            'branch_id' => ['nullable', 'integer', Rule::in($branchIds ?: [-1])],
            'status' => ['nullable', Rule::in([PrizePayout::STATUS_PENDING, PrizePayout::STATUS_PAID])],
        ]);
        $drawDate = $filters['draw_date'] ?? now()->toDateString();

        $payouts = PrizePayout::query()
            ->with(['branch', 'customer', 'drawResult.draw', 'intakeRequest', 'paidByUser'])
            ->whereIn('branch_id', $branchIds)
            ->whereHas('drawResult', fn ($query) => $query->whereDate('draw_date', $drawDate))
            ->when($filters['branch_id'] ?? null, fn ($query, $branchId) => $query->where('branch_id', $branchId))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->orderBy('status')
            ->orderByDesc('total_prize')
            ->get();

        return view('payouts.index', [
            'payouts' => $payouts,
            'branches' => Branch::query()->whereIn('id', $branchIds)->orderBy('name')->get(),
            'filters' => $filters,
            'drawDate' => $drawDate,
            'totals' => [
                'pending' => (float) $payouts->where('status', PrizePayout::STATUS_PENDING)->sum('total_prize'),
                'paid' => (float) $payouts->where('status', PrizePayout::STATUS_PAID)->sum('total_prize'),
            ],
        ]);
    }

    public function pay(Request $request, PrizePayout $payout): RedirectResponse
    {
        $this->authorize('pay', $payout);

        $payout->update([
            'status' => PrizePayout::STATUS_PAID,
            'paid_by' => $request->user()->id,
            'paid_at' => now(),
        ]);

        return back()->with('status', __('Prize of :number marked as paid.', ['number' => $payout->number]));
    }
}
