<?php

namespace App\Traits\Resources;

use Illuminate\Support\Facades\Date;

trait HasDates
{
    public function asDate(?string $date) : string {
        if (!$date) {
            return '';
        }

        //UTC ISO-8601
        return Date::parse($date)->format('Y-m-d\TH:i:s\Z');
    }
}