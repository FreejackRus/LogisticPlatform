<?php

namespace App\Policies;

use App\Models\DispatcherConnection;
use App\Models\User;

class DispatcherConnectionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isDispatcher() || $user->isAdmin();
    }

    public function view(User $user, DispatcherConnection $connection): bool
    {
        return $user->isAdmin() || $user->isDispatcher();
    }

    public function update(User $user, DispatcherConnection $connection): bool
    {
        return $user->isAdmin() || $user->isDispatcher();
    }
}
