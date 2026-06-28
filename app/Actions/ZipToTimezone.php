<?php

namespace App\Actions;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * ZipToTimezone
 * 
 * This action is used to get the timezone for all given zipcodes using the 'zip3_to_timezone' database.
 * 
 * 
 */
class ZipToTimezone
{
    use AsAction;

    public function handle(string|array $zipcodes): array
    {
        if (!is_array($zipcodes)) {
            $zipcodes = [$zipcodes];
        }

        $result = [];
        foreach ($zipcodes as $zipcode) {
            $result[$zipcode] = Cache::remember("zip3_to_timezone.{$zipcode}", 60, function () use ($zipcode) {
                $zip3 = substr($zipcode, 0, 3);

                $timezone = DB::connection('zip3_to_timezone')->table('zip3_to_timezone')->where('zip3', $zip3)->first();
                if (!$timezone) {
                    return null;
                }

                
                return [
                    'identifier' => $timezone->identifier,
                    'dst_tz' => $timezone->timezone_2,
                    'std_tz' => $timezone->timezone_1,
                    'is_dst' => $this->isDST(),
                    'dst_tz_offset' => $timezone->timezone_2_offset,
                    'std_tz_offset' => $timezone->timezone_1_offset,
                ];
            });
        }

        return $result;
    }

    public function asController(ActionRequest $request)
    {
        $zipcodes = $request->zipcodes ?? $request->zipcode;

        return $this->handle($zipcodes);
    }

    public function rules(): array
    {
        return [
            'zipcode' => ['nullable', 'string', 'min:3', 'required_without:zipcodes'],

            'zipcodes' => ['nullable', 'array', 'required_without:zipcode'],
            'zipcodes.*' => ['required', 'string', 'min:3'],
        ];
    }

    // Determine if DST is currently in effect
    private function isDST(): bool
    {
        $server_timezone = date_default_timezone_get();
        // Set the timezone to one we know observes DST because date() uses the server timezone
        date_default_timezone_set('America/New_York');
        $is_dst = date('I');
        // reset server timezone
        date_default_timezone_set($server_timezone);

        return $is_dst ? true : false;
    }
}
