<?php

namespace App\Actions\Utilities;

class FormatPhoneForE164
{
    public static function handle($phone, $countryCode = 'US', $returnNullOnFailure = false)
    {
        try {
            // If phone starts with +, Laravel Phone will auto-detect country
            // Otherwise, it will use the provided country code (default: US)
            $phoneNumber = phone($phone, $countryCode);
            
            // Format in E164 format for consistency
            $formatted_phone = $phoneNumber->formatE164();
        } catch (\Exception $e) {
            if ($returnNullOnFailure) {
                return null;
            }
            return $phone;
        }

        return $formatted_phone;
    }
}