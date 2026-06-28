<?php

namespace App\Http\Controllers\Freight;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CompanyController extends Controller
{
    public function edit(Request $request): Response
    {
        if ($request->user()->company) {
            Gate::authorize('view', $request->user()->company);
        } else {
            Gate::authorize('create', Company::class);
        }

        return Inertia::render('Freight/Company/Edit', [
            'company' => $request->user()->company?->load('carrierMembers'),
            'options' => config('freight.options'),
            'isCarrier' => $request->user()->isCarrier(),
            'canManageCarrierMembers' => $request->user()->company?->type === 'carrier'
                && $request->user()->company?->carrier_profile_type === 'company',
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->company) {
            Gate::authorize('update', $user->company);
        } else {
            Gate::authorize('create', Company::class);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'short_name' => ['nullable', 'string', 'max:255'],
            'inn' => ['nullable', 'string', 'max:20'],
            'kpp' => ['nullable', 'string', 'max:20'],
            'ogrn' => ['nullable', 'string', 'max:20'],
            'tax_system' => ['nullable', 'string', 'max:50'],
            'legal_address' => ['nullable', 'string', 'max:255'],
            'actual_address' => ['nullable', 'string', 'max:255'],
            'director_name' => ['nullable', 'string', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'bank_bik' => ['nullable', 'string', 'max:20'],
            'bank_account' => ['nullable', 'string', 'max:50'],
            'correspondent_account' => ['nullable', 'string', 'max:50'],
            'phone' => ['nullable', 'string', 'max:50', 'regex:/^\+?[0-9\s\-\(\)]{10,20}$/'],
            'email' => ['nullable', 'email', 'max:255'],
            'website' => ['nullable', 'url', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'carrier_profile_type' => ['nullable', 'in:individual,company'],
            'allows_carrier_members' => ['nullable', 'boolean'],
        ]);

        if (! $user->isCarrier()) {
            unset($data['carrier_profile_type'], $data['allows_carrier_members']);
        }

        $company = $user->company;
        $legalFields = [
            'name',
            'short_name',
            'inn',
            'kpp',
            'ogrn',
            'tax_system',
            'legal_address',
            'actual_address',
            'director_name',
            'bank_name',
            'bank_bik',
            'bank_account',
            'correspondent_account',
        ];
        $requiresReview = ! $company || $this->hasChangedLegalFields($company, $data, $legalFields);

        $status = $company?->verification_status ?? 'pending';
        if ($requiresReview) {
            $status = 'pending';
        }

        Company::updateOrCreate(
            ['user_id' => $user->id],
            [
                ...$data,
                'type' => $user->role,
                'carrier_profile_type' => $user->isCarrier()
                    ? ($data['carrier_profile_type'] ?? 'individual')
                    : 'individual',
                'allows_carrier_members' => $user->isCarrier()
                    && ($data['carrier_profile_type'] ?? 'individual') === 'company'
                    && (bool) ($data['allows_carrier_members'] ?? false),
                'verification_status' => $status,
                'verification_comment' => $requiresReview ? null : $company?->verification_comment,
                'verified_at' => $requiresReview ? null : $company?->verified_at,
                'rejected_at' => $requiresReview ? null : $company?->rejected_at,
            ],
        );

        return back()->with('status', 'Компания сохранена.');
    }

    public function addCarrierMember(Request $request): RedirectResponse
    {
        $company = $request->user()->company;

        abort_unless(
            $company && $company->type === 'carrier' && $company->carrier_profile_type === 'company',
            403,
        );

        $data = $request->validate([
            'email' => ['required', 'email', 'exists:users,email'],
            'role' => ['nullable', 'in:driver,manager'],
        ]);

        $carrier = User::where('email', $data['email'])->where('role', 'carrier')->firstOrFail();

        $company->carrierMembers()->syncWithoutDetaching([
            $carrier->id => [
                'role' => $data['role'] ?? 'driver',
                'status' => 'active',
                'joined_at' => now(),
            ],
        ]);

        return back()->with('status', 'Перевозчик добавлен в компанию.');
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, string>  $fields
     */
    private function hasChangedLegalFields(Company $company, array $data, array $fields): bool
    {
        foreach ($fields as $field) {
            if (($company->{$field} ?? null) !== ($data[$field] ?? null)) {
                return true;
            }
        }

        return false;
    }
}
