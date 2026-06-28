<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'type',
        'name',
        'short_name',
        'inn',
        'kpp',
        'ogrn',
        'tax_system',
        'legal_address',
        'actual_address',
        'director_name',
        'contact_person',
        'bank_name',
        'bank_bik',
        'bank_account',
        'correspondent_account',
        'phone',
        'email',
        'website',
        'description',
        'carrier_profile_type',
        'allows_carrier_members',
        'verification_status',
        'verification_comment',
        'verified_at',
        'rejected_at',
        'rating',
        'reviews_count',
        'is_blocked',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'decimal:2',
            'is_blocked' => 'boolean',
            'allows_carrier_members' => 'boolean',
            'verified_at' => 'datetime',
            'rejected_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function loads(): HasMany
    {
        return $this->hasMany(FreightLoad::class);
    }

    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class);
    }

    public function carrierMembers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'carrier_company_members', 'company_id', 'carrier_id')
            ->withPivot(['role', 'status', 'joined_at'])
            ->withTimestamps();
    }
}
