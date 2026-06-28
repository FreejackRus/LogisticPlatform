<?php

namespace App\Policies;

use App\Models\Company;
use App\Models\User;

class CompanyPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isShipper() || ($user->isCarrier() && ! $user->isCarrierCompanyDriver());
    }

    public function view(User $user, Company $company): bool
    {
        return $user->isAdmin() || $user->id === $company->user_id;
    }

    public function update(User $user, Company $company): bool
    {
        return $user->isAdmin() || $user->id === $company->user_id;
    }

    public function moderate(User $user, Company $company): bool
    {
        return $user->isAdmin();
    }
}
