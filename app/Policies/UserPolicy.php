<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->organization_id !== null && $user->assignableRoles() !== [];
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    /**
     * Owners edit everyone in their organization; admins only edit sellers and viewers.
     * Nobody edits their own account here (that is what the profile page is for).
     */
    public function update(User $user, User $target): bool
    {
        return $this->viewAny($user)
            && $user->organization_id === $target->organization_id
            && $user->id !== $target->id
            && in_array($target->role, $user->assignableRoles(), true);
    }
}
