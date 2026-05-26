<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Draw;
use App\Models\IntakeRequest;
use App\Models\NumberLimit;
use App\Models\Organization;

class NumberLimitService
{
    public function currentConfirmedAmount(Organization $organization, Branch $branch, ?Draw $draw, string $number): float
    {
        return $this->currentRequestAmount($organization, $branch, $draw, $number, [
            IntakeRequest::STATUS_CONFIRMED,
        ]);
    }

    public function currentRequestAmount(Organization $organization, Branch $branch, ?Draw $draw, string $number, array $statuses): float
    {
        return (float) IntakeRequest::query()
            ->where('organization_id', $organization->id)
            ->where('branch_id', $branch->id)
            ->when($draw, fn ($query) => $query->where('draw_id', $draw->id), fn ($query) => $query->whereNull('draw_id'))
            ->where('detected_number', $number)
            ->whereIn('status', $statuses)
            ->sum('detected_amount');
    }

    public function currentActiveAmount(Organization $organization, Branch $branch, ?Draw $draw, string $number): float
    {
        return $this->currentRequestAmount($organization, $branch, $draw, $number, [
            IntakeRequest::STATUS_CONFIRMED,
            IntakeRequest::STATUS_PENDING,
            IntakeRequest::STATUS_NEEDS_REVIEW,
        ]);
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
        if ($draw === null) {
            return $this->emptyDecision();
        }

        $limit = $this->limitFor($organization, $branch, $draw, $number);

        if ($limit === null) {
            return $this->emptyDecision();
        }

        if ($limit->is_blocked || $limit->restriction_type === NumberLimit::RESTRICTION_TYPE_BLOCKED) {
            $warning = 'Number is blocked for this draw. Manual review required.';

            return [
                'limit' => $limit,
                'status' => IntakeRequest::STATUS_NEEDS_REVIEW,
                'reason' => 'blocked',
                'notes' => $warning,
                'warning' => $warning,
                'customer_review_notice' => $warning,
            ];
        }

        if ($limit->requires_manual_review) {
            $warning = 'Number is restricted for this draw. Manual review required.';

            return [
                'limit' => $limit,
                'status' => IntakeRequest::STATUS_NEEDS_REVIEW,
                'reason' => 'manual_review',
                'notes' => $warning,
                'warning' => $warning,
                'customer_review_notice' => $warning,
            ];
        }

        $activeAmount = $this->currentActiveAmount($organization, $branch, $draw, $number);
        $projectedAmount = $activeAmount + $amount;

        if ($limit->max_amount <= 0 || $projectedAmount > (float) $limit->max_amount) {
            $warning = sprintf(
                'Limit warning: current active amount for %s on %s %s would exceed max %s%s.',
                $number,
                $branch->name,
                $draw->name,
                "\u{20A1}",
                $this->formatAmount($limit->max_amount),
            );

            return [
                'limit' => $limit,
                'status' => IntakeRequest::STATUS_NEEDS_REVIEW,
                'reason' => 'over_limit',
                'notes' => $warning,
                'warning' => $warning,
                'customer_review_notice' => null,
            ];
        }

        return $this->emptyDecision($limit);
    }

    public function warningForAmount(
        Organization $organization,
        Branch $branch,
        ?Draw $draw,
        string $number,
        float $amount,
    ): ?string {
        return $this->requestDecisionForAmount($organization, $branch, $draw, $number, $amount)['warning'];
    }

    public function statusFor(?NumberLimit $limit, float $activeAmount): string
    {
        if ($limit === null) {
            return 'no_limit';
        }

        if ($limit->is_blocked || $limit->restriction_type === NumberLimit::RESTRICTION_TYPE_BLOCKED) {
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

        if ($limit->requires_manual_review) {
            return 'manual_review';
        }

        if ($limit->is_restricted || $limit->restriction_type === NumberLimit::RESTRICTION_TYPE_RESTRICTED) {
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
     *     status: string,
     *     reason: ?string,
     *     notes: ?string,
     *     warning: ?string,
     *     customer_review_notice: ?string
     * }
     */
    private function emptyDecision(?NumberLimit $limit = null): array
    {
        return [
            'limit' => $limit,
            'status' => IntakeRequest::STATUS_PENDING,
            'reason' => null,
            'notes' => null,
            'warning' => null,
            'customer_review_notice' => null,
        ];
    }

    private function formatAmount(mixed $amount): string
    {
        if (is_numeric($amount) && (float) $amount === (float) (int) $amount) {
            return (string) (int) $amount;
        }

        return rtrim(rtrim(number_format((float) $amount, 2, '.', ''), '0'), '.');
    }
}
