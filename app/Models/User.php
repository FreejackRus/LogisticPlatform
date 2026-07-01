<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'role',
        'password',
        'is_active',
        'is_blocked',
        'last_login_at',
        'profile_photo_path',
        'timezone',
        'language_preference',
        'terms_accepted_at',
        'privacy_accepted_at',
        'platform_role_accepted_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'is_active' => 'boolean',
            'is_blocked' => 'boolean',
            'last_login_at' => 'datetime',
            'terms_accepted_at' => 'datetime',
            'privacy_accepted_at' => 'datetime',
            'platform_role_accepted_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Get the URL for the user's profile photo.
     *
     * @return string|null
     */
    public function getProfilePhotoUrlAttribute()
    {
        if ($this->profile_photo_path) {
            // Extract filename from path
            $filename = basename($this->profile_photo_path);
            return route('profile.photo', ['userId' => $this->id, 'filename' => $filename]);
        }

        return null;
    }

    public function company(): HasOne
    {
        return $this->hasOne(Company::class);
    }

    public function loads(): HasMany
    {
        return $this->hasMany(FreightLoad::class, 'shipper_id');
    }

    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class, 'carrier_id');
    }

    public function assignedVehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class, 'assigned_driver_id');
    }

    public function carrierCompanies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class, 'carrier_company_members', 'carrier_id', 'company_id')
            ->withPivot(['role', 'status', 'joined_at'])
            ->withTimestamps();
    }

    public function activeCarrierCompany(): ?Company
    {
        return $this->carrierCompanies()
            ->wherePivot('status', 'active')
            ->orderByPivot('joined_at', 'desc')
            ->first() ?? $this->company;
    }

    public function activeCarrierMemberRole(): ?string
    {
        $company = $this->carrierCompanies()
            ->wherePivot('status', 'active')
            ->orderByPivot('joined_at', 'desc')
            ->first();

        return $company?->pivot?->role;
    }

    public function isCarrierCompanyDriver(): bool
    {
        return $this->isCarrier() && $this->activeCarrierMemberRole() === 'driver';
    }

    public function canManageCarrierFleet(): bool
    {
        return $this->isCarrier() && ! $this->isCarrierCompanyDriver();
    }

    public function hasVerifiedBusinessProfile(): bool
    {
        $company = $this->isCarrier() ? $this->activeCarrierCompany() : $this->company;

        return (bool) $company
            && $company->verification_status === 'verified'
            && ! $company->is_blocked;
    }

    public function bids(): HasMany
    {
        return $this->hasMany(Bid::class, 'carrier_id');
    }

    public function freightNotifications(): HasMany
    {
        return $this->hasMany(FreightNotification::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isDispatcher(): bool
    {
        return $this->role === 'dispatcher';
    }

    public function isShipper(): bool
    {
        return $this->role === 'shipper';
    }

    public function isCarrier(): bool
    {
        return $this->role === 'carrier';
    }
}
