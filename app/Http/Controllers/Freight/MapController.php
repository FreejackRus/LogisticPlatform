<?php

namespace App\Http\Controllers\Freight;

use App\Http\Controllers\Controller;
use App\Models\FreightLoad;
use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class MapController extends Controller
{
    public function page(): Response
    {
        return Inertia::render('Freight/Map', [
            'map' => [
                'defaultLat' => config('freight.map.default_lat'),
                'defaultLng' => config('freight.map.default_lng'),
                'defaultZoom' => config('freight.map.default_zoom'),
                'refreshSeconds' => config('freight.map.refresh_seconds'),
                'tileUrl' => config('freight.map.tile_url'),
                'attribution' => config('freight.map.attribution'),
            ],
        ]);
    }

    public function objects(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'types' => ['nullable', 'array'],
            'types.*' => ['string', 'in:loads,vehicles'],
            'body_type' => ['nullable', 'string', 'max:80'],
            'online' => ['nullable', 'boolean'],
            'limit' => ['nullable', 'integer', 'min:20', 'max:500'],
            'bounds' => ['nullable', 'array'],
            'bounds.north' => ['required_with:bounds', 'numeric', 'between:-90,90'],
            'bounds.south' => ['required_with:bounds', 'numeric', 'between:-90,90'],
            'bounds.east' => ['required_with:bounds', 'numeric', 'between:-180,180'],
            'bounds.west' => ['required_with:bounds', 'numeric', 'between:-180,180'],
        ]);

        $types = collect($filters['types'] ?? ['loads', 'vehicles']);
        $limit = (int) ($filters['limit'] ?? 250);
        $bounds = $this->normalizedBounds($filters['bounds'] ?? null);
        $bodyType = $filters['body_type'] ?? null;

        $loads = $types->contains('loads') ? FreightLoad::query()
            ->active()
            ->with('company')
            ->whereNotNull('loading_lat')
            ->whereNotNull('loading_lng')
            ->when($bodyType, fn ($query) => $query->where('body_type', $bodyType))
            ->when($bounds, fn ($query) => $this->applyBounds($query, 'loading_lat', 'loading_lng', $bounds))
            ->latest('published_at')
            ->limit($limit)
            ->get()
            ->map(fn (FreightLoad $load) => [
                'id' => $load->id,
                'type' => 'load',
                'title' => $load->title,
                'lat' => (float) $load->loading_lat,
                'lng' => (float) $load->loading_lng,
                'city' => $load->loading_city,
                'route' => $load->loading_city.' -> '.$load->unloading_city,
                'body_type' => $load->body_type,
                'price' => $load->price,
                'url' => route('loads.show', $load),
            ]) : collect();

        $user = $request->user();

        $vehicles = $types->contains('vehicles') ? Vehicle::query()
            ->visibleOnMap()
            ->with('company')
            ->when($user?->isCarrier(), fn ($query) => $this->scopeCarrierVehicles($query, $user))
            ->when($bodyType, fn ($query) => $query->where('body_type', $bodyType))
            ->when(array_key_exists('online', $filters), fn ($query) => $query->where('is_online', (bool) $filters['online']))
            ->when($bounds, fn ($query) => $this->applyBounds($query, 'current_lat', 'current_lng', $bounds))
            ->latest('last_location_at')
            ->limit($limit)
            ->get()
            ->map(fn (Vehicle $vehicle) => [
                'id' => $vehicle->id,
                'type' => 'vehicle',
                'title' => $vehicle->title,
                'lat' => (float) $vehicle->current_lat,
                'lng' => (float) $vehicle->current_lng,
                'city' => $vehicle->current_city,
                'body_type' => $vehicle->body_type,
                'is_online' => $vehicle->is_online,
                'company' => $vehicle->company?->name,
                'url' => route('vehicles.show', $vehicle),
            ]) : collect();

        return response()->json([
            'loads' => $loads,
            'vehicles' => $vehicles,
            'filters' => [
                'types' => $types->values(),
                'body_type' => $bodyType,
                'online' => array_key_exists('online', $filters) ? (bool) $filters['online'] : null,
                'limit' => $limit,
                'bounded' => $bounds !== null,
            ],
            'generated_at' => now()->toIso8601String(),
        ]);
    }

    public function acceptedRoute(Request $request, FreightLoad $load): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 403);

        $bid = $load->bids()
            ->where('status', 'accepted')
            ->with('vehicle')
            ->when($user->isCarrier(), fn ($query) => $this->scopeAcceptedBidForCarrier($query, $user))
            ->first();

        abort_unless($bid && ($user->isCarrier() || $user->isDispatcher() || $user->isAdmin()), 403);
        abort_if(
            ! $bid->vehicle
            || ! $bid->vehicle->current_lat
            || ! $bid->vehicle->current_lng
            || ! $load->loading_lat
            || ! $load->loading_lng,
            422,
            'Для построения маршрута нужны координаты транспорта и точки погрузки.',
        );

        $route = $this->routeViaOsrm(
            (float) $bid->vehicle->current_lat,
            (float) $bid->vehicle->current_lng,
            (float) $load->loading_lat,
            (float) $load->loading_lng,
        );

        abort_if($route === null, 422, 'Не удалось построить маршрут.');

        return response()->json([
            ...$route,
            'load' => [
                'id' => $load->id,
                'title' => $load->title,
                'lat' => (float) $load->loading_lat,
                'lng' => (float) $load->loading_lng,
                'city' => $load->loading_city,
                'url' => route('loads.show', $load),
            ],
            'vehicle' => [
                'id' => $bid->vehicle->id,
                'title' => $bid->vehicle->title,
                'lat' => (float) $bid->vehicle->current_lat,
                'lng' => (float) $bid->vehicle->current_lng,
                'url' => route('vehicles.show', $bid->vehicle),
            ],
        ]);
    }

    /**
     * @return array{geometry: array<int, array{0: float, 1: float}>, distance_m: float|null, duration_s: float|null}|null
     */
    private function routeViaOsrm(float $fromLat, float $fromLng, float $toLat, float $toLng): ?array
    {
        try {
            $coordinates = "{$fromLng},{$fromLat};{$toLng},{$toLat}";
            $response = Http::timeout((int) config('freight.geocoding.request_timeout_seconds', 5))
                ->get(rtrim((string) config('services.osrm.base_url'), '/').'/route/v1/driving/'.$coordinates, [
                    'overview' => 'full',
                    'geometries' => 'geojson',
                    'steps' => 'false',
                ]);

            if (! $response->successful()) {
                return null;
            }

            $route = $response->json('routes.0');
            $rawCoordinates = data_get($route, 'geometry.coordinates', []);

            if (! is_array($rawCoordinates) || $rawCoordinates === []) {
                return null;
            }

            $geometry = collect($rawCoordinates)
                ->filter(fn ($point) => is_array($point) && count($point) >= 2 && is_numeric($point[0]) && is_numeric($point[1]))
                ->map(fn (array $point) => [(float) $point[1], (float) $point[0]])
                ->values()
                ->all();

            if ($geometry === []) {
                return null;
            }

            return [
                'geometry' => $geometry,
                'distance_m' => is_numeric(data_get($route, 'distance')) ? (float) data_get($route, 'distance') : null,
                'duration_s' => is_numeric(data_get($route, 'duration')) ? (float) data_get($route, 'duration') : null,
            ];
        } catch (\Throwable $exception) {
            Log::warning('OSRM route failed', [
                'message' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @param  array{north: numeric, south: numeric, east: numeric, west: numeric}|null  $bounds
     * @return array{north: float, south: float, east: float, west: float}|null
     */
    private function normalizedBounds(?array $bounds): ?array
    {
        if (! $bounds) {
            return null;
        }

        return [
            'north' => max((float) $bounds['north'], (float) $bounds['south']),
            'south' => min((float) $bounds['north'], (float) $bounds['south']),
            'east' => (float) $bounds['east'],
            'west' => (float) $bounds['west'],
        ];
    }

    /**
     * @param  array{north: float, south: float, east: float, west: float}  $bounds
     */
    private function applyBounds($query, string $latColumn, string $lngColumn, array $bounds): void
    {
        $query
            ->whereBetween($latColumn, [$bounds['south'], $bounds['north']])
            ->where(function ($query) use ($lngColumn, $bounds) {
                if ($bounds['west'] <= $bounds['east']) {
                    $query->whereBetween($lngColumn, [$bounds['west'], $bounds['east']]);

                    return;
                }

                $query
                    ->where($lngColumn, '>=', $bounds['west'])
                    ->orWhere($lngColumn, '<=', $bounds['east']);
            });
    }

    private function scopeCarrierVehicles($query, $user): void
    {
        if ($user->isCarrierCompanyDriver()) {
            $query->where(function ($query) use ($user) {
                $query->where('carrier_id', $user->id)
                    ->orWhere('assigned_driver_id', $user->id);
            });

            return;
        }

        $query->where(function ($query) use ($user) {
            $query->where('carrier_id', $user->id);

            if ($companyId = $user->activeCarrierCompany()?->id) {
                $query->orWhere('company_id', $companyId);
            }
        });
    }

    private function scopeAcceptedBidForCarrier($query, $user): void
    {
        if ($user->isCarrierCompanyDriver()) {
            $query->where(function ($query) use ($user) {
                $query->where('carrier_id', $user->id)
                    ->orWhereHas('vehicle', fn ($vehicle) => $vehicle->where('assigned_driver_id', $user->id));
            });

            return;
        }

        $query->where(function ($query) use ($user) {
            $query->where('carrier_id', $user->id);

            if ($companyId = $user->activeCarrierCompany()?->id) {
                $query->orWhere('company_id', $companyId);
            }
        });
    }
}
