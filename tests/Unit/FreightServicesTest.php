<?php

use App\Models\Vehicle;
use App\Services\DistanceService;
use App\Services\GeocodingService;
use App\Services\VehicleOnlineStatusService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

it('calculates haversine distance', function () {
    $distance = app(DistanceService::class)->distanceKm(55.7558, 37.6173, 59.9343, 30.3351);

    expect($distance)->toBeGreaterThan(630)->toBeLessThan(650);
});

it('geocodes through mocked photon response', function () {
    config([
        'freight.geocoding.provider' => 'photon',
        'services.photon.base_url' => 'https://photon.test',
    ]);
    Cache::flush();

    Http::fake([
        'photon.test/api*' => Http::response([
            'features' => [
                [
                    'geometry' => ['coordinates' => [37.6173, 55.7558]],
                    'properties' => [
                        'name' => 'Tverskaya Street',
                        'city' => 'Moscow',
                    ],
                ],
            ],
        ]),
    ]);

    expect(app(GeocodingService::class)->geocodeCity('Moscow', 'Tverskaya Street'))
        ->toMatchArray(['lat' => 55.7558, 'lng' => 37.6173]);
});

it('caches photon geocoding responses', function () {
    config([
        'freight.geocoding.provider' => 'photon',
        'services.photon.base_url' => 'https://photon.test',
    ]);
    Cache::flush();

    Http::fake([
        'photon.test/api*' => Http::response([
            'features' => [
                [
                    'geometry' => ['coordinates' => [37.6173, 55.7558]],
                    'properties' => [
                        'name' => 'Tverskaya Street',
                        'city' => 'Moscow',
                    ],
                ],
            ],
        ]),
    ]);

    app(GeocodingService::class)->geocodeCity('Moscow', 'Tverskaya Street');
    app(GeocodingService::class)->geocodeCity('Moscow', 'Tverskaya Street');

    Http::assertSentCount(1);
});

it('marks stale vehicles offline', function () {
    $carrier = freightUser('carrier');

    $fresh = Vehicle::create([
        'carrier_id' => $carrier->id,
        'title' => 'Fresh',
        'is_online' => true,
        'last_location_at' => now()->subMinute(),
    ]);
    $stale = Vehicle::create([
        'carrier_id' => $carrier->id,
        'title' => 'Stale',
        'is_online' => true,
        'last_location_at' => now()->subMinutes(10),
    ]);

    expect(app(VehicleOnlineStatusService::class)->markOffline())->toBe(1)
        ->and($fresh->refresh()->is_online)->toBeTrue()
        ->and($stale->refresh()->is_online)->toBeFalse();
});
