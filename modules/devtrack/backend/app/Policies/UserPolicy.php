<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->is_admin || $actor->canManageTeamAccounts();
    }

    public function view(User $actor, User $target): bool
    {
        if ($actor->is_admin) {
            return true;
        }

        if (! $actor->canManageTeamAccounts()) {
            return false;
        }

        return (int) $target->created_by === (int) $actor->id || (int) $target->id === (int) $actor->id;
    }

    public function create(User $actor): bool
    {
        return $actor->is_admin || $actor->canManageTeamAccounts();
    }

    public function update(User $actor, User $target): bool
    {
        if ($actor->is_admin) {
            return true;
        }

        if (! $actor->canManageTeamAccounts()) {
            return false;
        }

        // Partners may only manage accounts they created (not themselves via this form for safety on role fields).
        return (int) $target->created_by === (int) $actor->id && ! $target->is_admin;
    }

    public function delete(User $actor, User $target): bool
    {
        return $this->update($actor, $target);
    }
}
