<?php

use App\Services\GeocodingService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

it('returns address suggestions from photon provider', function () {
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
                        'name' => 'Тверская улица',
                        'housenumber' => '1',
                        'city' => 'Москва',
                        'country' => 'Россия',
                    ],
                ],
            ],
        ]),
    ]);

    $suggestions = app(GeocodingService::class)->suggestAddresses('Москва Тверская', 5);

    expect($suggestions)->toHaveCount(1)
        ->and($suggestions[0]['title'])->toBe('Тверская улица, 1')
        ->and($suggestions[0]['city'])->toBe('Москва')
        ->and($suggestions[0]['lat'])->toBe(55.7558)
        ->and($suggestions[0]['lng'])->toBe(37.6173);
});
