<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewFreightAdmin(User $user): bool
    {
        return $user->isAdmin();
    }

    public function moderateFreight(User $user, User $target): bool
    {
        return $user->isAdmin();
    }
}
