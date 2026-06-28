<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class GeocodingService
{
    /**
     * @return array{lat: float|null, lng: float|null}
     */
    public function geocodeCity(?string $city, ?string $address = null): array
    {
        $query = trim(implode(', ', array_filter([$city, $address])));

        if ($query !== '') {
            $result = Cache::remember(
                $this->cacheKey('geocode', $query),
                $this->cacheTtl(),
                fn () => $this->geocodeViaProvider($query),
            );

            if ($result['lat'] !== null && $result['lng'] !== null) {
                return $result;
            }
        }

        return $this->geocodeFromDictionary($city);
    }

    /**
     * @return array<int, array{title: string, subtitle: string|null, city: string|null, address: string|null, lat: float|null, lng: float|null}>
     */
    public function suggestAddresses(string $query, ?int $limit = null): array
    {
        $query = trim($query);

        if (mb_strlen($query) < 2) {
            return [];
        }

        $limit = max(1, min($limit ?? (int) config('freight.geocoding.suggest_limit', 6), 10));

        if ($this->provider() !== 'dictionary') {
            $suggestions = Cache::remember(
                $this->cacheKey('suggest', $query.'|'.$limit),
                $this->cacheTtl(),
                fn () => $this->suggestViaProvider($query, $limit),
            );

            if ($suggestions !== []) {
                return $suggestions;
            }
        }

        return $this->suggestFromDictionary($query, $limit);
    }

    /**
     * @return array{lat: float|null, lng: float|null}
     */
    private function geocodeViaProvider(string $query): array
    {
        return match ($this->provider()) {
            'photon' => $this->geocodeViaPhoton($query),
            'nominatim' => $this->geocodeViaNominatim($query),
            default => ['lat' => null, 'lng' => null],
        };
    }

    /**
     * @return array<int, array{title: string, subtitle: string|null, city: string|null, address: string|null, lat: float|null, lng: float|null}>
     */
    private function suggestViaProvider(string $query, int $limit): array
    {
        return match ($this->provider()) {
            'photon' => $this->suggestViaPhoton($query, $limit),
            default => [],
        };
    }

    /**
     * @return array{lat: float|null, lng: float|null}
     */
    private function geocodeViaPhoton(string $query): array
    {
        $feature = $this->photonFeatures($query, 1)[0] ?? null;

        if (! is_array($feature)) {
            return ['lat' => null, 'lng' => null];
        }

        return $this->coordinatesFromGeoJsonFeature($feature);
    }

    /**
     * @return array<int, array{title: string, subtitle: string|null, city: string|null, address: string|null, lat: float|null, lng: float|null}>
     */
    private function suggestViaPhoton(string $query, int $limit): array
    {
        return collect($this->photonFeatures($query, $limit))
            ->map(function (array $feature) {
                $properties = data_get($feature, 'properties', []);
                $coordinates = $this->coordinatesFromGeoJsonFeature($feature);
                $title = $this->photonTitle($properties);

                if ($title === null) {
                    return null;
                }

                $city = $this->photonCity($properties);
                $subtitle = $this->photonSubtitle($properties, $title);

                return [
                    'title' => $title,
                    'subtitle' => $subtitle,
                    'city' => $city,
                    'address' => trim(implode(', ', array_filter([$title, $subtitle]))),
                    'lat' => $coordinates['lat'],
                    'lng' => $coordinates['lng'],
                ];
            })
            ->filter()
            ->take($limit)
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function photonFeatures(string $query, int $limit): array
    {
        try {
            $response = Http::timeout((int) config('freight.geocoding.request_timeout_seconds', 5))
                ->get(rtrim((string) config('services.photon.base_url'), '/').'/api', [
                    'q' => $query,
                    'lang' => 'ru',
                    'limit' => $limit,
                ]);

            if (! $response->successful()) {
                return [];
            }

            $features = $response->json('features', []);

            return is_array($features) ? $features : [];
        } catch (\Throwable $exception) {
            Log::warning('Photon geocoding failed', [
                'query' => $query,
                'message' => $exception->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * @return array{lat: float|null, lng: float|null}
     */
    private function geocodeViaNominatim(string $query): array
    {
        try {
            $response = Http::timeout((int) config('freight.geocoding.request_timeout_seconds', 5))
                ->withHeaders(['User-Agent' => (string) config('services.nominatim.user_agent')])
                ->get(rtrim((string) config('services.nominatim.base_url'), '/').'/search', [
                    'q' => $query,
                    'format' => 'jsonv2',
                    'limit' => 1,
                    'addressdetails' => 1,
                    'countrycodes' => 'ru',
                ]);

            if (! $response->successful()) {
                return ['lat' => null, 'lng' => null];
            }

            $result = $response->json('0', []);
            $lat = data_get($result, 'lat');
            $lng = data_get($result, 'lon');

            return is_numeric($lat) && is_numeric($lng)
                ? ['lat' => (float) $lat, 'lng' => (float) $lng]
                : ['lat' => null, 'lng' => null];
        } catch (\Throwable $exception) {
            Log::warning('Nominatim geocoding failed', [
                'query' => $query,
                'message' => $exception->getMessage(),
            ]);

            return ['lat' => null, 'lng' => null];
        }
    }

    /**
     * @return array{lat: float|null, lng: float|null}
     */
    private function geocodeFromDictionary(?string $city): array
    {
        $cities = $this->cityDictionary();

        if ($city && isset($cities[$city])) {
            return ['lat' => $cities[$city][0], 'lng' => $cities[$city][1]];
        }

        return ['lat' => null, 'lng' => null];
    }

    /**
     * @return array<int, array{title: string, subtitle: string|null, city: string|null, address: string|null, lat: float|null, lng: float|null}>
     */
    private function suggestFromDictionary(string $query, int $limit): array
    {
        $needle = Str::lower($query);

        return collect($this->cityDictionary())
            ->filter(fn (array $coordinates, string $city) => Str::contains(Str::lower($city), $needle))
            ->map(fn (array $coordinates, string $city) => [
                'title' => $city,
                'subtitle' => 'Россия',
                'city' => $city,
                'address' => $city,
                'lat' => $coordinates[0],
                'lng' => $coordinates[1],
            ])
            ->take($limit)
            ->values()
            ->all();
    }

    /**
     * @return array<string, array{0: float, 1: float}>
     */
    private function cityDictionary(): array
    {
        return [
            'Москва' => [55.7558, 37.6173],
            'Санкт-Петербург' => [59.9343, 30.3351],
            'Казань' => [55.7961, 49.1064],
            'Нижний Новгород' => [56.2965, 43.9361],
            'Екатеринбург' => [56.8389, 60.6057],
            'Новосибирск' => [55.0084, 82.9357],
            'Ростов-на-Дону' => [47.2357, 39.7015],
            'Краснодар' => [45.0355, 38.9753],
            'Самара' => [53.1959, 50.1008],
            'Уфа' => [54.7388, 55.9721],
            'Пермь' => [58.0105, 56.2502],
            'Воронеж' => [51.6608, 39.2003],
            'Тюмень' => [57.1530, 65.5343],
            'Челябинск' => [55.1644, 61.4368],
            'Омск' => [54.9885, 73.3242],
            'Красноярск' => [56.0153, 92.8932],
            'Владивосток' => [43.1155, 131.8855],
            'Иркутск' => [52.2864, 104.2807],
            'Саратов' => [51.5336, 46.0343],
            'Волгоград' => [48.7080, 44.5133],
        ];
    }

    private function cacheKey(string $scope, string $query): string
    {
        return 'freight:geocoding:'.$this->provider().':'.$scope.':'.sha1(Str::lower(trim($query)));
    }

    private function cacheTtl(): int
    {
        return max(60, (int) config('freight.geocoding.cache_ttl_seconds', 86400));
    }

    private function provider(): string
    {
        $provider = Str::lower((string) config('freight.geocoding.provider', 'photon'));

        return in_array($provider, ['photon', 'nominatim', 'dictionary'], true)
            ? $provider
            : 'photon';
    }

    /**
     * @return array{lat: float|null, lng: float|null}
     */
    private function coordinatesFromGeoJsonFeature(array $feature): array
    {
        $coordinates = data_get($feature, 'geometry.coordinates', []);

        if (! is_array($coordinates) || count($coordinates) < 2 || ! is_numeric($coordinates[0]) || ! is_numeric($coordinates[1])) {
            return ['lat' => null, 'lng' => null];
        }

        return ['lat' => (float) $coordinates[1], 'lng' => (float) $coordinates[0]];
    }

    private function photonTitle(array $properties): ?string
    {
        $parts = array_filter([
            data_get($properties, 'name'),
            data_get($properties, 'housenumber'),
        ], fn ($value) => is_string($value) && $value !== '');

        $title = trim(implode(', ', $parts));

        return $title !== '' ? $title : null;
    }

    private function photonCity(array $properties): ?string
    {
        foreach (['city', 'town', 'village', 'municipality', 'county', 'state'] as $key) {
            $value = data_get($properties, $key);

            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    private function photonSubtitle(array $properties, string $title): ?string
    {
        $subtitle = collect([
            data_get($properties, 'street'),
            $this->photonCity($properties),
            data_get($properties, 'state'),
            data_get($properties, 'country'),
        ])
            ->filter(fn ($value) => is_string($value) && $value !== '' && $value !== $title)
            ->unique()
            ->implode(', ');

        return $subtitle !== '' ? $subtitle : null;
    }
}
