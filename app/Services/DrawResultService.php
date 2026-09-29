<?php

namespace App\Services;

use App\Models\Draw;
use App\Models\DrawResult;
use App\Models\IntakeRequest;
use App\Models\PrizePayout;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Records the winning number of a draw on a date and derives who won and how much.
 */
class DrawResultService
{
    public function __construct(
        private readonly CustomerMessengerService $customerMessengerService,
    ) {
    }

    public function record(Draw $draw, string $drawDate, string $winningNumber, bool $reventadoHit, User $user, ?string $notes = null): DrawResult
    {
        return DB::transaction(function () use ($draw, $drawDate, $winningNumber, $reventadoHit, $user, $notes): DrawResult {
            $result = DrawResult::query()
                ->where('draw_id', $draw->id)
                ->whereDate('draw_date', $drawDate)
                ->lockForUpdate()
                ->first();

            if ($result?->isLocked()) {
                throw ValidationException::withMessages([
                    'winning_number' => __('This result can no longer be changed: winners were already notified or paid.'),
                ]);
            }

            $result ??= new DrawResult([
                'organization_id' => $draw->organization_id,
                'draw_id' => $draw->id,
                'draw_date' => $drawDate,
            ]);

            $result->fill([
                'winning_number' => $winningNumber,
                'reventado_hit' => $draw->offersReventado() && $reventadoHit,
                'prize_multiplier' => $draw->prize_multiplier,
                'reventado_multiplier' => $draw->offersReventado() ? $draw->reventado_multiplier : null,
                'entered_by' => $user->id,
                'notes' => $notes,
            ])->save();

            $this->recalculatePayouts($result);

            return $result->fresh(['payouts']);
        });
    }

    /**
     * Sends the winner message to every winner who has a customer chat. Afterwards the result is locked.
     *
     * @return int number of winners notified
     */
    public function notifyWinners(DrawResult $result, User $user): int
    {
        $notified = 0;

        foreach ($result->payouts()->with('intakeRequest.incomingMessage', 'intakeRequest.draw', 'branch')->get() as $payout) {
            if ($this->customerMessengerService->notifyWinner($payout, $user) !== null) {
                $notified++;
            }
        }

        $result->update(['winners_notified_at' => now()]);

        return $notified;
    }

    private function recalculatePayouts(DrawResult $result): void
    {
        // Only unpaid payouts exist at this point (locked results never get here).
        $result->payouts()->delete();

        $winners = IntakeRequest::query()
            ->where('draw_id', $result->draw_id)
            ->whereDate('draw_date', $result->draw_date->toDateString())
            ->where('status', IntakeRequest::STATUS_CONFIRMED)
            ->where('detected_number', $result->winning_number)
            ->get();

        foreach ($winners as $request) {
            $betAmount = (float) $request->detected_amount;
            $reventadoAmount = (float) ($request->reventado_amount ?? 0);
            $prize = round($betAmount * (float) $result->prize_multiplier, 2);
            $reventadoPrize = $result->reventado_hit && $reventadoAmount > 0 && $result->reventado_multiplier !== null
                ? round($reventadoAmount * (float) $result->reventado_multiplier, 2)
                : 0.0;

            $result->payouts()->create([
                'organization_id' => $result->organization_id,
                'branch_id' => $request->branch_id,
                'intake_request_id' => $request->id,
                'customer_id' => $request->customer_id,
                'number' => $request->detected_number,
                'bet_amount' => $betAmount,
                'prize_amount' => $prize,
                'reventado_amount' => $reventadoAmount,
                'reventado_prize' => $reventadoPrize,
                'total_prize' => $prize + $reventadoPrize,
                'status' => PrizePayout::STATUS_PENDING,
            ]);
        }
    }
}
