<?php

namespace App\Policies;

use App\Models\DrawResult;
use App\Models\User;

class DrawResultPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->organization_id !== null;
    }

    /**
     * Only owners and admins enter or correct winning numbers.
     */
    public function create(User $user): bool
    {
        return $user->organization_id !== null && $user->canViewAllBranches();
    }

    public function notify(User $user, DrawResult $result): bool
    {
        return $this->create($user) && $user->organization_id === $result->organization_id;
    }
}
