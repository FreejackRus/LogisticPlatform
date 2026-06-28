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
        return $user->id === $bid->carrier_id && $bid->status === 'pending';
    }
}
