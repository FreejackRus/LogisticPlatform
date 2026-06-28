<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Valid Countries for Phone Numbers
    |--------------------------------------------------------------------------
    |
    | A comma-separated list of ISO 3166-1 alpha-2 country codes that are 
    | considered valid for phone number validation.
    |
    */
    'valid_countries' => env('VALID_PHONE_COUNTRIES', 'US,CA'),
]; 