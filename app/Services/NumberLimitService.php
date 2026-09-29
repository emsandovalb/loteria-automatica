<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Draw;
use App\Models\IntakeRequest;
use App\Models\NumberLimit;
use App\Models\Organization;

class NumberLimitService
{
    public function currentConfirmedAmount(Organization $organization, Branch $branch, ?Draw $draw, string $number, ?string $drawDate = null): float
    {
        return $this->currentRequestAmount($organization, $branch, $draw, $number, [
            IntakeRequest::STATUS_CONFIRMED,
        ], $drawDate);
    }

    /**
     * Sum of amounts for one number on one draw and business day. Without a draw date the
     * draw's current operating date is used, so limits never accumulate across days.
     */
    public function currentRequestAmount(
        Organization $organization,
        Branch $branch,
        ?Draw $draw,
        string $number,
        array $statuses,
        ?string $drawDate = null,
        ?int $excludeRequestId = null,
    ): float {
        $drawDate ??= $draw?->operatingDate() ?? now()->toDateString();

        return (float) IntakeRequest::query()
            ->where('organization_id', $organization->id)
            ->where('branch_id', $branch->id)
            ->when($draw, fn ($query) => $query->where('draw_id', $draw->id), fn ($query) => $query->whereNull('draw_id'))
            ->whereDate('draw_date', $drawDate)
            ->where('detected_number', $number)
            ->whereIn('status', $statuses)
            ->when($excludeRequestId, fn ($query) => $query->whereKeyNot($excludeRequestId))
            ->sum('detected_amount');
    }

    public function currentActiveAmount(
        Organization $organization,
        Branch $branch,
        ?Draw $draw,
        string $number,
        ?string $drawDate = null,
        ?int $excludeRequestId = null,
    ): float {
        return $this->currentRequestAmount($organization, $branch, $draw, $number, [
            IntakeRequest::STATUS_CONFIRMED,
            IntakeRequest::STATUS_PENDING,
            IntakeRequest::STATUS_NEEDS_REVIEW,
        ], $drawDate, $excludeRequestId);
    }

    public function limitFor(Organization $organization, Branch $branch, Draw $draw, string $number): ?NumberLimit
    {
        return NumberLimit::query()
            ->where('organization_id', $organization->id)
            ->where('branch_id', $branch->id)
            ->where('draw_id', $draw->id)
            ->where('number', $number)
            ->first();
    }

    /**
     * @return array{
     *     limit: ?NumberLimit,
     *     status: string,
     *     reason: ?string,
     *     notes: ?string,
     *     warning: ?string,
     *     customer_review_notice: ?string
     * }
     */
    public function requestDecisionForAmount(
        Organization $organization,
        Branch $branch,
        ?Draw $draw,
        string $number,
        float $amount,
    ): array {
        $evaluation = $this->evaluateRequestForAmount($organization, $branch, $draw, $number, $amount);

        return [
            'limit' => $evaluation['limit'],
            'status' => $evaluation['final_status'],
            'reason' => $evaluation['reason'],
            'notes' => $evaluation['note'],
            'warning' => $evaluation['note'],
            'customer_review_notice' => $evaluation['customer_review_notice'],
            'is_blocked' => $evaluation['is_blocked'],
            'requires_manual_review' => $evaluation['requires_manual_review'],
            'is_over_limit' => $evaluation['is_over_limit'],
            'final_status' => $evaluation['final_status'],
            'note' => $evaluation['note'],
        ];
    }

    public function warningForAmount(
        Organization $organization,
        Branch $branch,
        ?Draw $draw,
        string $number,
        float $amount,
    ): ?string {
        return $this->evaluateRequestForAmount($organization, $branch, $draw, $number, $amount)['note'];
    }

    public function statusFor(?NumberLimit $limit, float $activeAmount): string
    {
        if ($limit === null) {
            return 'no_limit';
        }

        if ($this->isBlockedLimit($limit)) {
            return 'blocked';
        }

        $maxAmount = (float) $limit->max_amount;

        if ($maxAmount <= 0) {
            return $activeAmount > 0 ? 'over_limit' : 'full';
        }

        $usagePercentage = round(($activeAmount / $maxAmount) * 100, 6);

        if ($usagePercentage > 100) {
            return 'over_limit';
        }

        if ($usagePercentage === 100.0) {
            return 'full';
        }

        if ($usagePercentage >= 80) {
            return 'warning';
        }

        if ($this->requiresManualReviewLimit($limit)) {
            return 'manual_review';
        }

        if ($this->isRestrictedLimit($limit)) {
            return 'restricted';
        }

        if ($limit->restriction_type === NumberLimit::RESTRICTION_TYPE_HOT) {
            return 'hot';
        }

        return 'available';
    }

    /**
     * @return array{available_amount: float|null, percentage_used: float|null, status: string, is_restricted: bool, restriction_type: ?string, requires_manual_review: bool, is_blocked: bool}
     */
    public function limitStateFor(?NumberLimit $limit, float $activeAmount): array
    {
        if ($limit === null) {
            return [
                'available_amount' => null,
                'percentage_used' => null,
                'status' => 'no_limit',
                'is_restricted' => false,
                'restriction_type' => null,
                'requires_manual_review' => false,
                'is_blocked' => false,
            ];
        }

        $maxAmount = (float) $limit->max_amount;

        return [
            'available_amount' => $maxAmount - $activeAmount,
            'percentage_used' => $maxAmount > 0
                ? round(($activeAmount / $maxAmount) * 100, 1)
                : null,
            'status' => $this->statusFor($limit, $activeAmount),
            'is_restricted' => (bool) $limit->is_restricted,
            'restriction_type' => $limit->restriction_type,
            'requires_manual_review' => (bool) $limit->requires_manual_review,
            'is_blocked' => (bool) $limit->is_blocked,
        ];
    }

    /**
     * @return array{
     *     limit: ?NumberLimit,
     *     is_blocked: bool,
     *     requires_manual_review: bool,
     *     is_over_limit: bool,
     *     final_status: string,
     *     note: ?string,
     *     reason: ?string,
     *     customer_review_notice: ?string
     * }
     */
    public function evaluateRequestForAmount(
        Organization $organization,
        Branch $branch,
        ?Draw $draw,
        string $number,
        float $amount,
        ?string $drawDate = null,
        ?int $excludeRequestId = null,
    ): array {
        $limit = $draw === null
            ? null
            : $this->limitFor($organization, $branch, $draw, $number);

        $evaluation = $this->emptyEvaluation($limit);

        if ($limit === null) {
            return $evaluation;
        }

        $evaluation['is_blocked'] = $this->isBlockedLimit($limit);
        $evaluation['requires_manual_review'] = $this->requiresManualReviewLimit($limit) || $this->isRestrictedLimit($limit);

        $activeAmount = $this->currentActiveAmount($organization, $branch, $draw, $number, $drawDate, $excludeRequestId);
        $evaluation['is_over_limit'] = $this->isOverLimit($limit, $activeAmount, $amount);

        if ($evaluation['is_blocked']) {
            $note = 'Number is blocked for this draw. Manual review required.';

            $evaluation['final_status'] = IntakeRequest::STATUS_NEEDS_REVIEW;
            $evaluation['note'] = $note;
            $evaluation['reason'] = 'blocked';
            $evaluation['customer_review_notice'] = $note;

            return $evaluation;
        }

        if ($evaluation['requires_manual_review']) {
            $note = 'Number is restricted for this draw. Manual review required.';

            $evaluation['final_status'] = IntakeRequest::STATUS_NEEDS_REVIEW;
            $evaluation['note'] = $note;
            $evaluation['reason'] = 'manual_review';
            $evaluation['customer_review_notice'] = $note;

            return $evaluation;
        }

        if ($evaluation['is_over_limit']) {
            $note = __('Limit warning: current active amount for :number on :branch :draw would exceed max ₡:max.', [
                'number' => $number,
                'branch' => $branch->name,
                'draw' => $draw->name,
                'max' => $this->formatAmount($limit->max_amount),
            ]);

            $evaluation['final_status'] = IntakeRequest::STATUS_NEEDS_REVIEW;
            $evaluation['note'] = $note;
            $evaluation['reason'] = 'over_limit';

            return $evaluation;
        }

        return $evaluation;
    }

    /**
     * @return array{
     *     limit: ?NumberLimit,
     *     is_blocked: bool,
     *     requires_manual_review: bool,
     *     is_over_limit: bool,
     *     final_status: string,
     *     note: ?string,
     *     reason: ?string,
     *     customer_review_notice: ?string
     * }
     */
    private function emptyEvaluation(?NumberLimit $limit = null): array
    {
        return [
            'limit' => $limit,
            'is_blocked' => false,
            'requires_manual_review' => false,
            'is_over_limit' => false,
            'final_status' => IntakeRequest::STATUS_PENDING,
            'reason' => null,
            'note' => null,
            'customer_review_notice' => null,
        ];
    }

    private function isBlockedLimit(?NumberLimit $limit): bool
    {
        return $limit !== null
            && ($limit->is_blocked || $limit->restriction_type === NumberLimit::RESTRICTION_TYPE_BLOCKED);
    }

    private function isRestrictedLimit(?NumberLimit $limit): bool
    {
        return $limit !== null
            && ($limit->is_restricted || $limit->restriction_type === NumberLimit::RESTRICTION_TYPE_RESTRICTED);
    }

    private function requiresManualReviewLimit(?NumberLimit $limit): bool
    {
        return $limit !== null && (bool) $limit->requires_manual_review;
    }

    private function isOverLimit(NumberLimit $limit, float $activeAmount, float $amount): bool
    {
        $maxAmount = (float) $limit->max_amount;
        $projectedAmount = $activeAmount + $amount;

        return $maxAmount <= 0 || $projectedAmount > $maxAmount;
    }

    private function formatAmount(mixed $amount): string
    {
        if (is_numeric($amount) && (float) $amount === (float) (int) $amount) {
            return (string) (int) $amount;
        }

        return rtrim(rtrim(number_format((float) $amount, 2, '.', ''), '0'), '.');
    }
}
