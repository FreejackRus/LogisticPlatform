<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Vehicle;

class VehiclePolicy
{
    public function create(User $user): bool
    {
        return $user->canManageCarrierFleet();
    }

    public function manage(User $user, Vehicle $vehicle): bool
    {
        return $user->isAdmin()
            || $user->id === $vehicle->carrier_id
            || (
                $user->canManageCarrierFleet()
                && $user->activeCarrierCompany()?->id
                && $vehicle->company_id === $user->activeCarrierCompany()->id
            );
    }

    public function update(User $user, Vehicle $vehicle): bool
    {
        return $this->manage($user, $vehicle);
    }

    public function updateLocation(User $user, Vehicle $vehicle): bool
    {
        return $user->id === $vehicle->carrier_id || $user->id === $vehicle->assigned_driver_id;
    }

    public function useForBid(User $user, Vehicle $vehicle): bool
    {
        return $vehicle->is_available
            && (
                $user->id === $vehicle->carrier_id
                || $user->id === $vehicle->assigned_driver_id
                || (
                    $user->canManageCarrierFleet()
                    && $user->activeCarrierCompany()?->id
                    && $vehicle->company_id === $user->activeCarrierCompany()->id
                )
            );
    }

    public function moderate(User $user, Vehicle $vehicle): bool
    {
        return $user->isAdmin();
    }
}
