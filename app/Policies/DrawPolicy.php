<?php

namespace App\Policies;

use App\Models\Draw;
use App\Models\User;

class DrawPolicy
{
    public function viewAny(User $user): bool
    {
        return (bool) $user->organization_id;
    }

    public function view(User $user, Draw $draw): bool
    {
        return $user->organization_id === $draw->organization_id;
    }

    public function update(User $user, Draw $draw): bool
    {
        return $user->organization_id === $draw->organization_id && $user->canViewAllBranches();
    }
}
