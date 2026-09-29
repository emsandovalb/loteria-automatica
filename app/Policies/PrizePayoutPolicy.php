<?php

namespace App\Policies;

use App\Models\PrizePayout;
use App\Models\User;

class PrizePayoutPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->organization_id !== null;
    }

    /**
     * The branch that sold the bet pays it; owners and admins can register any payment.
     */
    public function pay(User $user, PrizePayout $payout): bool
    {
        if ($user->organization_id !== $payout->organization_id || $payout->isPaid()) {
            return false;
        }

        if ($user->canViewAllBranches()) {
            return true;
        }

        return $user->isSeller() && $user->branch_id !== null && $user->branch_id === $payout->branch_id;
    }
}
