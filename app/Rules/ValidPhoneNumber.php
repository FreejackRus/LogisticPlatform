<?php

namespace App\Rules;

use App\Actions\Utilities\FormatPhoneForE164;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidPhoneNumber implements ValidationRule
{
    protected array $countries;

    public function __construct()
    {
        $this->countries = explode(',', trim(config('phones.valid_countries')));
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!$value) {
            return;
        }

        // If phone starts with +, it's international - try to validate without country restriction
        if (str_starts_with(trim($value), '+')) {
            $formatted = FormatPhoneForE164::handle($value, null, true);
            if ($formatted !== null) {
                return;
            }
        }

        // Otherwise, check against allowed countries
        $isValid = false;
        foreach ($this->countries as $country) {
            $formatted = FormatPhoneForE164::handle($value, trim($country), true);
            if ($formatted !== null) {
                $isValid = true;
                break;
            }
        }

        if (!$isValid) {
            $fail('The :attribute field must be a valid phone number within a supported country (' . implode(', ', $this->countries) .')');
        }
    }
} 