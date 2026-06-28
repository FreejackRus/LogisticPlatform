<?php

namespace App\Policies;

use App\Models\Complaint;
use App\Models\User;

class ComplaintPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isDispatcher() || $user->isShipper() || $user->isCarrier();
    }

    public function create(User $user): bool
    {
        return $user->isShipper() || $user->isCarrier() || $user->isDispatcher();
    }

    public function view(User $user, Complaint $complaint): bool
    {
        return $user->isAdmin() || $user->id === $complaint->reporter_id;
    }

    public function update(User $user, Complaint $complaint): bool
    {
        return $user->isAdmin();
    }
}
