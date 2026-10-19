<?php

namespace App\Policies;

use App\Models\ExternalIntegration;
use App\Models\User;

class ExternalIntegrationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active && $user->isApproved();
    }

    public function view(User $user, ExternalIntegration $externalIntegration): bool
    {
        return $user->is_admin || $user->canAccessExternalIntegration($externalIntegration->id);
    }

    public function create(User $user): bool
    {
        return $user->canRegisterClients();
    }

    public function update(User $user, ExternalIntegration $externalIntegration): bool
    {
        return $this->view($user, $externalIntegration);
    }

    public function delete(User $user, ExternalIntegration $externalIntegration): bool
    {
        // Active clients are rejected in the controller with a clear 422 message.
        return $this->view($user, $externalIntegration);
    }
}
