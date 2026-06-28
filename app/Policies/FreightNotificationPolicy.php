<?php

namespace App\Policies;

use App\Models\FreightNotification;
use App\Models\User;

class FreightNotificationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isDispatcher() || $user->isShipper() || $user->isCarrier();
    }

    public function view(User $user, FreightNotification $notification): bool
    {
        return $user->id === $notification->user_id;
    }

    public function update(User $user, FreightNotification $notification): bool
    {
        return $this->view($user, $notification);
    }
}
