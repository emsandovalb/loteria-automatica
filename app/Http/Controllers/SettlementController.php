<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Draw;
use App\Models\DrawResult;
use App\Models\IntakeRequest;
use App\Models\PrizePayout;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Per branch and draw: confirmed sales minus prizes for one business day.
 */
class SettlementController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        abort_if($user->organization_id === null, 403);

        $validated = $request->validate([
            'draw_date' => ['nullable', 'date_format:Y-m-d'],
        ]);
        $drawDate = $validated['draw_date'] ?? now()->toDateString();
        $branchIds = $user->visibleBranchIds();

        $branches = Branch::query()->whereIn('id', $branchIds)->orderBy('name')->get();
        $draws = Draw::query()
            ->where('organization_id', $user->organization_id)
            ->orderBy('draw_time')
            ->get();

        $sales = IntakeRequest::query()
            ->whereIn('branch_id', $branchIds)
            ->whereDate('draw_date', $drawDate)
            ->where('status', IntakeRequest::STATUS_CONFIRMED)
            ->whereNotNull('draw_id')
            ->groupBy('branch_id', 'draw_id')
            ->select('branch_id', 'draw_id', DB::raw('SUM(detected_amount + COALESCE(reventado_amount, 0)) as sales'))
            ->get()
            ->keyBy(fn ($row) => $row->branch_id . ':' . $row->draw_id);

        $prizes = PrizePayout::query()
            ->join('draw_results', 'draw_results.id', '=', 'prize_payouts.draw_result_id')
            ->whereIn('prize_payouts.branch_id', $branchIds)
            ->whereDate('draw_results.draw_date', $drawDate)
            ->groupBy('prize_payouts.branch_id', 'draw_results.draw_id')
            ->select('prize_payouts.branch_id', 'draw_results.draw_id', DB::raw('SUM(prize_payouts.total_prize) as prizes'))
            ->get()
            ->keyBy(fn ($row) => $row->branch_id . ':' . $row->draw_id);

        $results = DrawResult::query()
            ->where('organization_id', $user->organization_id)
            ->whereDate('draw_date', $drawDate)
            ->get()
            ->keyBy('draw_id');

        $rows = [];
        $totals = ['sales' => 0.0, 'prizes' => 0.0, 'net' => 0.0];

        foreach ($branches as $branch) {
            $branchRows = [];
            $branchTotals = ['sales' => 0.0, 'prizes' => 0.0, 'net' => 0.0];

            foreach ($draws as $draw) {
                $key = $branch->id . ':' . $draw->id;
                $sale = (float) ($sales[$key]->sales ?? 0);
                $prize = (float) ($prizes[$key]->prizes ?? 0);

                if ($sale === 0.0 && $prize === 0.0) {
                    continue;
                }

                $branchRows[] = [
                    'draw' => $draw,
                    'result' => $results[$draw->id] ?? null,
                    'sales' => $sale,
                    'prizes' => $prize,
                    'net' => $sale - $prize,
                ];
                $branchTotals['sales'] += $sale;
                $branchTotals['prizes'] += $prize;
                $branchTotals['net'] += $sale - $prize;
            }

            $rows[] = ['branch' => $branch, 'draws' => $branchRows, 'totals' => $branchTotals];

            foreach ($totals as $field => $value) {
                $totals[$field] = $value + $branchTotals[$field];
            }
        }

        return view('settlement.index', [
            'rows' => $rows,
            'totals' => $totals,
            'drawDate' => $drawDate,
        ]);
    }
}
