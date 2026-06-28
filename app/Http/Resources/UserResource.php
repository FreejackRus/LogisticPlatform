<?php

namespace App\Http\Resources;

use App\Traits\Resources\HasDates;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\User
 */
class UserResource extends JsonResource
{
    use HasDates;
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $activeCarrierCompany = $this->isCarrier() ? $this->activeCarrierCompany() : null;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'role' => $this->role,
            'is_active' => $this->is_active,
            'is_blocked' => $this->is_blocked,
            'created_at' => $this->asDate($this->created_at),
            'updated_at' => $this->asDate($this->updated_at),
            'profile_photo_url' => $this->profile_photo_url,
            'timezone' => $this->timezone,
            'language_preference' => $this->language_preference,
            'carrier_member_role' => $this->isCarrier() ? $this->activeCarrierMemberRole() : null,
            'can_manage_carrier_fleet' => $this->isCarrier() ? $this->canManageCarrierFleet() : false,
            'is_carrier_company_driver' => $this->isCarrier() ? $this->isCarrierCompanyDriver() : false,
            'active_carrier_company' => $activeCarrierCompany ? [
                'id' => $activeCarrierCompany->id,
                'name' => $activeCarrierCompany->name,
                'carrier_profile_type' => $activeCarrierCompany->carrier_profile_type,
            ] : null,
        ];
    }
}
