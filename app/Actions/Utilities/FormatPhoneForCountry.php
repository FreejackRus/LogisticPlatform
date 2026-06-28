<?php

namespace App\Actions\Utilities;

class FormatPhoneForCountry
{
    public static function handle($phone, $countryCode = 'US', $returnNullOnFailure = false)
    {
        try {
            if (empty($phone)) {
                return $returnNullOnFailure ? null : $phone;
            }

            // Parse the phone number (Laravel Phone handles E164 input well)
            $phoneNumber = phone($phone, $countryCode);
            
            // Format based on country - US/Canada get national format, others get international
            if ($phoneNumber->getCountry() === 'US' || $phoneNumber->getCountry() === 'CA') {
                $formatted_phone = $phoneNumber->formatNational();
            } else {
                $formatted_phone = $phoneNumber->formatInternational();
            }
        } catch (\Exception $e) {
            if ($returnNullOnFailure) {
                return null;
            }
            return $phone;
        }

        return $formatted_phone;
    }
}