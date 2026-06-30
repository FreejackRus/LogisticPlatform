<?php

namespace App\Policies;

use App\Models\Bid;
use App\Models\User;

class BidPolicy
{
    public function accept(User $user, Bid $bid): bool
    {
        $bid->loadMissing('freightLoad');

        return $user->id === $bid->freightLoad->shipper_id
            && $bid->freightLoad->status === 'active'
            && $bid->status === 'pending';
    }

    public function cancel(User $user, Bid $bid): bool
    {
        if ($bid->status !== 'pending') {
            return false;
        }

        if ($user->id === $bid->carrier_id) {
            return true;
        }

        return $user->canManageCarrierFleet()
            && $bid->company_id
            && $user->activeCarrierCompany()?->id === $bid->company_id;
    }
}
