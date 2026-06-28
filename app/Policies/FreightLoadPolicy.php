<?php

namespace App\Policies;

use App\Models\FreightLoad;
use App\Models\User;

class FreightLoadPolicy
{
    public function create(User $user): bool
    {
        return $user->isShipper();
    }

    public function update(User $user, FreightLoad $load): bool
    {
        return $user->isAdmin() || $user->id === $load->shipper_id;
    }

    public function edit(User $user, FreightLoad $load): bool
    {
        if (! $this->update($user, $load)) {
            return false;
        }

        return $user->isAdmin() || ! in_array($load->status, ['completed', 'archived'], true);
    }

    public function publish(User $user, FreightLoad $load): bool
    {
        return $this->update($user, $load) && in_array($load->status, ['draft', 'cancelled'], true);
    }

    public function cancel(User $user, FreightLoad $load): bool
    {
        return $this->update($user, $load) && in_array($load->status, ['draft', 'active', 'in_progress'], true);
    }

    public function complete(User $user, FreightLoad $load): bool
    {
        return $this->update($user, $load) && $load->status === 'in_progress';
    }

    public function respond(User $user, FreightLoad $load): bool
    {
        return $user->isCarrier()
            && $load->status === 'active'
            && $load->shipper_id !== $user->id;
    }

    public function dispatch(User $user, FreightLoad $load): bool
    {
        return ($user->isDispatcher() || $user->isAdmin()) && $load->status === 'active';
    }

    public function moderate(User $user, FreightLoad $load): bool
    {
        return $user->isAdmin();
    }
}
