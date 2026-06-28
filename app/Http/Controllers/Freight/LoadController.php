<?php

namespace App\Http\Controllers\Freight;

use App\Http\Controllers\Controller;
use App\Models\Bid;
use App\Models\DeliveryEvent;
use App\Models\FreightLoad;
use App\Models\FreightNotification;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\FreightMediaService;
use App\Services\GeocodingService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class LoadController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'from_city' => ['nullable', 'string', 'max:255'],
            'to_city' => ['nullable', 'string', 'max:255'],
            'body_type' => ['nullable', 'string', 'max:255'],
            'payment_type' => ['nullable', 'in:cash,bank_transfer,card,negotiable'],
            'min_price' => ['nullable', 'integer', 'min:0'],
            'max_price' => ['nullable', 'integer', 'min:0'],
            'urgent' => ['nullable', 'boolean'],
            'sort' => ['nullable', 'in:newest,price_asc,price_desc'],
        ]);

        $query = FreightLoad::query()
            ->with(['company', 'shipper'])
            ->active();

        if ($filters['q'] ?? null) {
            $search = $filters['q'];
            $query->where(function ($builder) use ($search) {
                $builder->where('title', 'like', '%'.$search.'%')
                    ->orWhere('cargo_type', 'like', '%'.$search.'%')
                    ->orWhere('cargo_description', 'like', '%'.$search.'%')
                    ->orWhere('loading_city', 'like', '%'.$search.'%')
                    ->orWhere('unloading_city', 'like', '%'.$search.'%');
            });
        }

        if ($filters['from_city'] ?? null) {
            $query->where('loading_city', 'like', '%'.$filters['from_city'].'%');
        }

        if ($filters['to_city'] ?? null) {
            $query->where('unloading_city', 'like', '%'.$filters['to_city'].'%');
        }

        if ($filters['body_type'] ?? null) {
            $query->where('body_type', $filters['body_type']);
        }

        if ($filters['payment_type'] ?? null) {
            $query->where('payment_type', $filters['payment_type']);
        }

        if ($filters['min_price'] ?? null) {
            $query->where('price', '>=', $filters['min_price']);
        }

        if ($filters['max_price'] ?? null) {
            $query->where('price', '<=', $filters['max_price']);
        }

        if ($request->boolean('urgent')) {
            $query->where('is_urgent', true);
        }

        match ($filters['sort'] ?? 'newest') {
            'price_asc' => $query->orderByRaw('price is null')->orderBy('price'),
            'price_desc' => $query->orderByDesc('price'),
            default => $query->latest('is_urgent')->latest('published_at'),
        };

        $filterOptions = [
            'bodyTypes' => FreightLoad::query()
                ->active()
                ->whereNotNull('body_type')
                ->distinct()
                ->orderBy('body_type')
                ->pluck('body_type')
                ->values(),
        ];

        $stats = [
            'total' => FreightLoad::query()->active()->count(),
            'urgent' => FreightLoad::query()->active()->where('is_urgent', true)->count(),
        ];

        return Inertia::render('Freight/Loads/Index', [
            'loads' => $query->paginate(20)->withQueryString()->through(
                fn (FreightLoad $load) => $this->loadIndexPayload($load, $request->user()),
            ),
            'filters' => $filters,
            'filterOptions' => $filterOptions,
            'stats' => $stats,
            'canCreateLoad' => (bool) $request->user()?->can('create', FreightLoad::class),
        ]);
    }

    public function mine(Request $request): Response
    {
        $user = $request->user();
        Gate::authorize('create', FreightLoad::class);

        $status = $request->validate([
            'status' => ['nullable', 'in:all,draft,active,in_progress,completed,cancelled,archived'],
        ])['status'] ?? 'all';

        $baseQuery = FreightLoad::query()->where('shipper_id', $user->id);
        $loadsQuery = (clone $baseQuery)
            ->with(['company', 'bids.carrier.company', 'bids.vehicle', 'deliveryEvents'])
            ->withCount([
                'bids as pending_bids_count' => fn ($query) => $query->where('status', 'pending'),
            ])
            ->latest();

        if ($status !== 'all') {
            $loadsQuery->where('status', $status);
        }

        $statusCounts = (clone $baseQuery)
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return Inertia::render('Freight/Loads/Mine', [
            'loads' => $loadsQuery->paginate(12)->withQueryString()->through(
                fn (FreightLoad $load) => $this->shipperLoadPayload($load),
            ),
            'currentStatus' => $status,
            'statusCounts' => [
                'all' => (clone $baseQuery)->count(),
                'draft' => (int) ($statusCounts['draft'] ?? 0),
                'active' => (int) ($statusCounts['active'] ?? 0),
                'in_progress' => (int) ($statusCounts['in_progress'] ?? 0),
                'completed' => (int) ($statusCounts['completed'] ?? 0),
                'cancelled' => (int) ($statusCounts['cancelled'] ?? 0),
                'archived' => (int) ($statusCounts['archived'] ?? 0),
            ],
        ]);
    }

    public function bids(Request $request, FreightLoad $load): Response
    {
        Gate::authorize('update', $load);
        abort_unless($request->user()->id === $load->shipper_id, 403);

        $load->load([
            'company',
            'bids.carrier.company',
            'bids.company',
            'bids.vehicle.assignedDriver',
            'deliveryEvents.actor',
        ]);

        $acceptedBid = $this->acceptedContractBid($load);

        return Inertia::render('Freight/Loads/Bids', [
            'load' => [
                ...$this->shipperLoadPayload($load),
                'company' => [
                    'name' => $load->company?->name,
                    'verification_status' => $load->company?->verification_status,
                ],
                'urls' => [
                    'show' => route('loads.show', $load),
                    'edit' => route('loads.edit', $load),
                    'contract' => $acceptedBid ? route('loads.contract', $load) : null,
                    'candidates' => route('loads.bids', $load),
                ],
            ],
            'bids' => $load->bids
                ->sortByDesc(fn (Bid $bid) => (($bid->status === 'accepted' ? 3 : ($bid->status === 'pending' ? 2 : 1)) * 10_000_000_000) + ($bid->created_at?->timestamp ?? 0))
                ->values()
                ->map(fn (Bid $bid) => $this->shipperBidPayload($load, $bid)),
            'canAcceptBids' => $load->status === 'active',
        ]);
    }

    public function delivery(Request $request, FreightLoad $load): Response
    {
        Gate::authorize('update', $load);
        abort_unless($request->user()->id === $load->shipper_id, 403);

        $load->load([
            'company',
            'shipper',
            'bids.carrier.company',
            'bids.company',
            'bids.vehicle.assignedDriver',
            'deliveryEvents.actor',
        ]);

        $acceptedBid = $this->acceptedContractBid($load);
        abort_unless($acceptedBid, 404);

        return Inertia::render('Freight/Loads/Delivery', [
            'delivery' => $this->shipperDeliveryPayload($load, $acceptedBid),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Freight/Loads/Create', [
            'disclaimer' => config('freight.legal_disclaimer'),
            'options' => config('freight.options'),
        ]);
    }

    public function store(Request $request, GeocodingService $geocoding, FreightMediaService $media): RedirectResponse
    {
        $user = $request->user();
        Gate::authorize('create', FreightLoad::class);

        $data = $this->validateLoad($request);
        $publish = $request->boolean('publish');
        unset($data['publish'], $data['cargo_photo']);

        $loading = $geocoding->geocodeCity($data['loading_city'] ?? null, $data['loading_address'] ?? null);
        $unloading = $geocoding->geocodeCity($data['unloading_city'] ?? null, $data['unloading_address'] ?? null);
        $cargoPhoto = $this->storePhoto($request, 'cargo_photo', 'loads', $media);

        $load = FreightLoad::create([
            ...$data,
            'shipper_id' => $user->id,
            'company_id' => $user->company?->id,
            'loading_lat' => $data['loading_lat'] ?? $loading['lat'],
            'loading_lng' => $data['loading_lng'] ?? $loading['lng'],
            'unloading_lat' => $data['unloading_lat'] ?? $unloading['lat'],
            'unloading_lng' => $data['unloading_lng'] ?? $unloading['lng'],
            'cargo_photo_path' => $cargoPhoto['path'] ?? null,
            'cargo_photo_meta' => $cargoPhoto['meta'] ?? null,
            'delivery_confirmation_token' => Str::random(40),
            'delivery_confirmation_code' => (string) random_int(100000, 999999),
            'price_currency' => 'RUB',
            'status' => $publish ? 'active' : 'draft',
            'published_at' => $publish ? now() : null,
        ]);

        return redirect()->route('loads.show', $load)->with('status', 'Груз создан.');
    }

    public function show(Request $request, FreightLoad $load): Response
    {
        $load->load(['company', 'shipper', 'bids.carrier.company', 'bids.vehicle.assignedDriver', 'deliveryEvents.actor']);
        $canSeeContacts = $this->canSeeShipperContacts($request->user(), $load);
        $acceptedRouteBid = $this->acceptedRouteBid($request->user(), $load);

        if (! $request->user() || $request->user()->id !== $load->shipper_id) {
            $load->increment('views_count');
        }

        return Inertia::render('Freight/Loads/Show', [
            'load' => $this->loadShowPayload($load, $canSeeContacts, $request->user()),
            'disclaimer' => config('freight.legal_disclaimer'),
            'contractText' => config('freight.contracts.text'),
            'canSeeContacts' => $canSeeContacts,
            'canBid' => (bool) $request->user()?->can('respond', $load),
            'canManage' => (bool) $request->user()?->can('update', $load),
            'canPublish' => (bool) $request->user()?->can('publish', $load),
            'canCancel' => (bool) $request->user()?->can('cancel', $load),
            'canComplete' => (bool) $request->user()?->can('complete', $load),
            'isDispatcher' => $request->user()?->isDispatcher() || $request->user()?->isAdmin(),
            'routeToLoadUrl' => $acceptedRouteBid
                ? route('map', ['load_id' => $load->id, 'route' => 1])
                : null,
            'carrierVehicles' => $request->user()?->isCarrier()
                ? $this->carrierVehiclesForBid($request->user(), $load)
                : [],
        ]);
    }

    public function edit(Request $request, FreightLoad $load): Response
    {
        Gate::authorize('edit', $load);

        return Inertia::render('Freight/Loads/Edit', [
            'load' => $load,
            'disclaimer' => config('freight.legal_disclaimer'),
            'options' => config('freight.options'),
        ]);
    }

    public function contract(Request $request, FreightLoad $load)
    {
        $load->load(['company', 'shipper', 'bids.carrier.company', 'bids.company', 'bids.vehicle.assignedDriver']);
        $bid = $this->acceptedContractBid($load);

        abort_unless($bid, 404);
        abort_unless($this->canViewContract($request->user(), $load, $bid), 403);

        return Inertia::render('Freight/Loads/Contract', [
            'contract' => $this->contractPayload($load, $bid),
            'downloadUrl' => route('loads.contract.download', $load),
            'loadUrl' => route('loads.show', $load),
            'platformDisclaimer' => config('freight.legal_disclaimer'),
        ]);
    }

    public function contractDownload(Request $request, FreightLoad $load)
    {
        $load->load(['company', 'shipper', 'bids.carrier.company', 'bids.company', 'bids.vehicle.assignedDriver']);
        $bid = $this->acceptedContractBid($load);

        abort_unless($bid, 404);
        abort_unless($this->canViewContract($request->user(), $load, $bid), 403);

        return Pdf::loadView('freight.contract', [
            'load' => $load,
            'bid' => $bid,
            'termsVersion' => config('freight.contracts.terms_version'),
            'platformDisclaimer' => config('freight.legal_disclaimer'),
            'generatedAt' => now(),
        ])
            ->setPaper('a4')
            ->download("freight-contract-load-{$load->id}.pdf");
    }

    public function update(Request $request, FreightLoad $load, GeocodingService $geocoding, FreightMediaService $media): RedirectResponse
    {
        Gate::authorize('update', $load);

        $data = $this->validateLoad($request);
        unset($data['publish'], $data['cargo_photo']);

        $loading = $geocoding->geocodeCity($data['loading_city'] ?? null, $data['loading_address'] ?? null);
        $unloading = $geocoding->geocodeCity($data['unloading_city'] ?? null, $data['unloading_address'] ?? null);
        $cargoPhoto = $this->storePhoto($request, 'cargo_photo', 'loads', $media, $load->cargo_photo_path);

        $load->update([
            ...$data,
            'loading_lat' => $data['loading_lat'] ?? $loading['lat'],
            'loading_lng' => $data['loading_lng'] ?? $loading['lng'],
            'unloading_lat' => $data['unloading_lat'] ?? $unloading['lat'],
            'unloading_lng' => $data['unloading_lng'] ?? $unloading['lng'],
            'cargo_photo_path' => $cargoPhoto['path'] ?? $load->cargo_photo_path,
            'cargo_photo_meta' => $cargoPhoto['meta'] ?? $load->cargo_photo_meta,
        ]);

        return redirect()->route('loads.show', $load)->with('status', 'Груз обновлен.');
    }

    public function publish(Request $request, FreightLoad $load): RedirectResponse
    {
        Gate::authorize('publish', $load);

        $load->update(['status' => 'active', 'published_at' => now()]);

        return back()->with('status', 'Груз опубликован.');
    }

    public function cancel(Request $request, FreightLoad $load): RedirectResponse
    {
        Gate::authorize('cancel', $load);

        $load->update(['status' => 'cancelled', 'cancelled_at' => now()]);
        $load->load('bids.vehicle');
        $this->releaseAcceptedVehicles($load);
        $this->notifyAcceptedBidUsers($load, 'load_cancelled', 'Груз отменён', 'Заказчик отменил груз '.$load->title.'.');

        return back()->with('status', 'Груз отменен.');
    }

    public function complete(Request $request, FreightLoad $load): RedirectResponse
    {
        Gate::authorize('complete', $load);

        $data = $request->validate([
            'delivery_confirmation' => ['required', 'string', 'max:64'],
        ]);

        $confirmation = trim($data['delivery_confirmation']);
        abort_unless(
            hash_equals((string) $load->delivery_confirmation_token, $confirmation)
                || hash_equals((string) $load->delivery_confirmation_code, $confirmation),
            422,
            'Код подтверждения доставки не совпадает.',
        );

        $load->update([
            'status' => 'completed',
            'delivery_stage' => 'delivery_confirmed',
            'completed_at' => now(),
            'completion_confirmed_at' => now(),
            'completion_confirmed_by' => $request->user()->id,
        ]);

        DeliveryEvent::create([
            'load_id' => $load->id,
            'bid_id' => $this->acceptedContractBid($load)?->id,
            'actor_id' => $request->user()->id,
            'type' => 'delivery_confirmed',
        ]);

        $load->load('bids.vehicle');
        $this->releaseAcceptedVehicles($load);
        $this->notifyAcceptedBidUsers($load, 'load_completed', 'Доставка подтверждена', 'Заказчик подтвердил завершение доставки по грузу '.$load->title.'.');

        return back()->with('status', 'Груз завершен.');
    }

    private function validateLoad(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'cargo_type' => ['nullable', 'string', 'max:255'],
            'cargo_description' => ['nullable', 'string', 'max:2000'],
            'loading_city' => ['required', 'string', 'max:255'],
            'loading_region' => ['nullable', 'string', 'max:255'],
            'loading_address' => ['nullable', 'string', 'max:255'],
            'loading_lat' => ['nullable', 'numeric'],
            'loading_lng' => ['nullable', 'numeric'],
            'unloading_city' => ['required', 'string', 'max:255'],
            'unloading_region' => ['nullable', 'string', 'max:255'],
            'unloading_address' => ['nullable', 'string', 'max:255'],
            'unloading_lat' => ['nullable', 'numeric'],
            'unloading_lng' => ['nullable', 'numeric'],
            'loading_date' => ['nullable', 'date'],
            'loading_time_from' => ['nullable', 'date_format:H:i'],
            'loading_time_to' => ['nullable', 'date_format:H:i'],
            'unloading_date' => ['nullable', 'date'],
            'unloading_time_from' => ['nullable', 'date_format:H:i'],
            'unloading_time_to' => ['nullable', 'date_format:H:i'],
            'weight_kg' => ['nullable', 'integer', 'min:1'],
            'volume_m3' => ['nullable', 'numeric', 'min:0'],
            'places_count' => ['nullable', 'integer', 'min:1'],
            'body_type' => ['nullable', 'string', 'max:255'],
            'loading_type' => ['nullable', 'string', 'max:255'],
            'temperature_mode' => ['nullable', 'string', 'max:255'],
            'price' => ['nullable', 'integer', 'min:0'],
            'price_with_vat' => ['boolean'],
            'payment_type' => ['nullable', 'in:cash,bank_transfer,card,negotiable'],
            'payment_terms' => ['nullable', 'string', 'max:255'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:50', 'regex:/^\+?[0-9\s\-\(\)]{10,20}$/'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'cargo_photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'is_urgent' => ['boolean'],
            'publish' => ['boolean'],
        ]);
    }

    private function canSeeShipperContacts(?User $user, FreightLoad $load): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->isAdmin() || $user->isDispatcher() || $user->id === $load->shipper_id) {
            return true;
        }

        if (! $user->isCarrier()) {
            return false;
        }

        return $load->bids()
            ->where(fn ($query) => $query
                ->where('carrier_id', $user->id)
                ->orWhereHas('vehicle', fn ($vehicle) => $vehicle->where('assigned_driver_id', $user->id))
                ->when(
                    $user->canManageCarrierFleet() && $user->activeCarrierCompany()?->id,
                    fn ($query) => $query->orWhere('company_id', $user->activeCarrierCompany()->id),
                ))
            ->where('status', 'accepted')
            ->exists();
    }

    private function acceptedRouteBid(?User $user, FreightLoad $load): ?Bid
    {
        if (! $user || ! $user->isCarrier() || ! $load->loading_lat || ! $load->loading_lng) {
            return null;
        }

        return $load->bids
            ->first(fn ($bid) => $this->canAccessCarrierBid($user, $bid)
                && $bid->status === 'accepted'
                && $bid->vehicle
                && $bid->vehicle->current_lat
                && $bid->vehicle->current_lng);
    }

    private function acceptedContractBid(FreightLoad $load): ?Bid
    {
        return $load->bids->first(fn ($bid) => $bid->status === 'accepted');
    }

    private function canViewContract(?User $user, FreightLoad $load, Bid $bid): bool
    {
        if (! $user) {
            return false;
        }

        return $user->isAdmin()
            || $user->isDispatcher()
            || $user->id === $load->shipper_id
            || $user->id === $bid->carrier_id
            || $user->id === $bid->vehicle?->assigned_driver_id
            || (
                $user->canManageCarrierFleet()
                && $user->activeCarrierCompany()?->id
                && $bid->company_id === $user->activeCarrierCompany()->id
            );
    }

    private function loadIndexPayload(FreightLoad $load, ?User $user): array
    {
        $canSeeContacts = $this->canSeeShipperContacts($user, $load);

        return [
            'id' => $load->id,
            'title' => $load->title,
            'cargo_type' => $load->cargo_type,
            'loading_city' => $load->loading_city,
            'unloading_city' => $load->unloading_city,
            'loading_date' => $this->formatDate($load->loading_date),
            'body_type' => $load->body_type,
            'weight_kg' => $load->weight_kg,
            'volume_m3' => $load->volume_m3,
            'price' => $load->price,
            'payment_type' => $load->payment_type,
            'cargo_photo_url' => $this->publicUrl($load->cargo_photo_path),
            'bids_count' => $load->bids_count,
            'views_count' => $load->views_count,
            'is_urgent' => $load->is_urgent,
            'can_see_contacts' => $canSeeContacts,
            'company' => [
                'name' => $load->company?->name,
                'phone' => $canSeeContacts ? $load->company?->phone : null,
            ],
        ];
    }

    private function shipperLoadPayload(FreightLoad $load): array
    {
        $acceptedBid = $load->bids->first(fn ($bid) => $bid->status === 'accepted');
        $latestEvent = $load->deliveryEvents->sortByDesc('id')->first();

        return [
            'id' => $load->id,
            'title' => $load->title,
            'status' => $load->status,
            'delivery_stage' => $load->delivery_stage,
            'loading_city' => $load->loading_city,
            'unloading_city' => $load->unloading_city,
            'loading_date' => $this->formatDate($load->loading_date),
            'unloading_date' => $this->formatDate($load->unloading_date),
            'price' => $load->price,
            'price_currency' => $load->price_currency,
            'body_type' => $load->body_type,
            'bids_count' => $load->bids_count,
            'pending_bids_count' => (int) ($load->pending_bids_count ?? 0),
            'views_count' => $load->views_count,
            'is_urgent' => $load->is_urgent,
            'created_at' => $this->formatDateTime($load->created_at),
            'published_at' => $this->formatDateTime($load->published_at),
            'completed_at' => $this->formatDateTime($load->completed_at),
            'accepted_bid' => $acceptedBid ? [
                'id' => $acceptedBid->id,
                'carrier_name' => $acceptedBid->carrier?->company?->name ?: $acceptedBid->carrier?->name,
                'vehicle_title' => $acceptedBid->vehicle?->title,
                'contract_signed_at' => $this->formatDateTime($acceptedBid->contract_signed_at),
            ] : null,
            'latest_event' => $latestEvent ? [
                'type' => $latestEvent->type,
                'created_at' => $this->formatDateTime($latestEvent->created_at),
            ] : null,
            'urls' => [
                'show' => route('loads.show', $load),
                'edit' => route('loads.edit', $load),
                'contract' => $acceptedBid ? route('loads.contract', $load) : null,
                'candidates' => route('loads.bids', $load),
                'delivery' => $acceptedBid ? route('loads.delivery', $load) : null,
            ],
        ];
    }

    private function shipperDeliveryPayload(FreightLoad $load, Bid $bid): array
    {
        $latestEvent = $load->deliveryEvents->sortByDesc('id')->first();

        return [
            'load' => [
                'id' => $load->id,
                'title' => $load->title,
                'status' => $load->status,
                'delivery_stage' => $load->delivery_stage,
                'loading_city' => $load->loading_city,
                'loading_region' => $load->loading_region,
                'loading_address' => $load->loading_address,
                'unloading_city' => $load->unloading_city,
                'unloading_region' => $load->unloading_region,
                'unloading_address' => $load->unloading_address,
                'loading_date' => $this->formatDate($load->loading_date),
                'unloading_date' => $this->formatDate($load->unloading_date),
                'cargo_type' => $load->cargo_type,
                'body_type' => $load->body_type,
                'weight_kg' => $load->weight_kg,
                'volume_m3' => $load->volume_m3,
                'places_count' => $load->places_count,
                'price' => $load->price,
                'price_currency' => $load->price_currency,
                'payment_type' => $load->payment_type,
                'payment_terms' => $load->payment_terms,
                'cargo_photo_url' => $this->publicUrl($load->cargo_photo_path),
                'completed_at' => $this->formatDateTime($load->completed_at),
                'completion_confirmed_at' => $this->formatDateTime($load->completion_confirmed_at),
                'urls' => [
                    'show' => route('loads.show', $load),
                    'contract' => route('loads.contract', $load),
                    'complete' => route('loads.complete', $load),
                    'event' => route('loads.delivery-events.store', $load),
                ],
            ],
            'carrier' => [
                'bid_id' => $bid->id,
                'status' => $bid->status,
                'contract_accepted_at' => $this->formatDateTime($bid->contract_accepted_at),
                'contract_signed_at' => $this->formatDateTime($bid->contract_signed_at),
                'carrier_cargo_photo_url' => $this->publicUrl($bid->carrier_cargo_photo_path),
                'contact' => [
                    'name' => $bid->carrier?->name,
                    'phone' => $bid->carrier?->phone ?: $bid->company?->phone ?: $bid->carrier?->company?->phone,
                    'email' => $bid->carrier?->email ?: $bid->company?->email ?: $bid->carrier?->company?->email,
                ],
                'company' => [
                    'name' => $bid->company?->name ?: $bid->carrier?->company?->name,
                    'phone' => $bid->company?->phone ?: $bid->carrier?->company?->phone,
                    'email' => $bid->company?->email ?: $bid->carrier?->company?->email,
                    'verification_status' => $bid->company?->verification_status ?: $bid->carrier?->company?->verification_status,
                    'rating' => $bid->company?->rating ?: $bid->carrier?->company?->rating,
                    'reviews_count' => $bid->company?->reviews_count ?: $bid->carrier?->company?->reviews_count,
                ],
                'vehicle' => $bid->vehicle ? [
                    'id' => $bid->vehicle->id,
                    'title' => $bid->vehicle->title,
                    'registration_number' => $bid->vehicle->registration_number,
                    'body_type' => $bid->vehicle->body_type,
                    'capacity_kg' => $bid->vehicle->capacity_kg,
                    'volume_m3' => $bid->vehicle->volume_m3,
                    'assigned_driver' => $bid->vehicle->assignedDriver ? [
                        'name' => $bid->vehicle->assignedDriver->name,
                        'phone' => $bid->vehicle->assignedDriver->phone,
                    ] : null,
                    'url' => route('vehicles.show', $bid->vehicle),
                ] : null,
            ],
            'latest_event' => $latestEvent ? $this->deliveryEventPayload($latestEvent) : null,
            'events' => $load->deliveryEvents
                ->sortByDesc('id')
                ->map(fn (DeliveryEvent $event) => $this->deliveryEventPayload($event))
                ->values(),
            'canComplete' => (bool) request()->user()?->can('complete', $load),
            'canUpdateDelivery' => $load->status === 'in_progress',
            'deliveryEventOptions' => $load->status === 'in_progress'
                ? DeliveryEvent::STAFF_EVENT_TYPES
                : [],
        ];
    }

    private function deliveryEventPayload(DeliveryEvent $event): array
    {
        return [
            'id' => $event->id,
            'type' => $event->type,
            'note' => $event->note,
            'created_at' => $this->formatDateTime($event->created_at),
            'actor' => [
                'name' => $event->actor?->name,
                'role' => $event->actor?->role,
            ],
        ];
    }

    private function shipperBidPayload(FreightLoad $load, Bid $bid): array
    {
        return [
            'id' => $bid->id,
            'status' => $bid->status,
            'comment' => $bid->comment,
            'created_at' => $this->formatDateTime($bid->created_at),
            'accepted_at' => $this->formatDateTime($bid->accepted_at),
            'rejected_at' => $this->formatDateTime($bid->rejected_at),
            'cancelled_at' => $this->formatDateTime($bid->cancelled_at),
            'contract_accepted_at' => $this->formatDateTime($bid->contract_accepted_at),
            'contract_signed_at' => $this->formatDateTime($bid->contract_signed_at),
            'can_accept' => $load->status === 'active' && $bid->status === 'pending',
            'carrier_cargo_photo_url' => $this->publicUrl($bid->carrier_cargo_photo_path),
            'carrier' => [
                'id' => $bid->carrier?->id,
                'name' => $bid->carrier?->name,
                'phone' => $bid->carrier?->phone,
                'email' => $bid->carrier?->email,
            ],
            'company' => [
                'id' => $bid->company?->id ?: $bid->carrier?->company?->id,
                'name' => $bid->company?->name ?: $bid->carrier?->company?->name,
                'phone' => $bid->company?->phone ?: $bid->carrier?->company?->phone,
                'email' => $bid->company?->email ?: $bid->carrier?->company?->email,
                'verification_status' => $bid->company?->verification_status ?: $bid->carrier?->company?->verification_status,
                'rating' => $bid->company?->rating ?: $bid->carrier?->company?->rating,
                'reviews_count' => $bid->company?->reviews_count ?: $bid->carrier?->company?->reviews_count,
            ],
            'vehicle' => $bid->vehicle ? [
                'id' => $bid->vehicle->id,
                'title' => $bid->vehicle->title,
                'vehicle_type' => $bid->vehicle->vehicle_type,
                'body_type' => $bid->vehicle->body_type,
                'registration_number' => $bid->vehicle->registration_number,
                'capacity_kg' => $bid->vehicle->capacity_kg,
                'volume_m3' => $bid->vehicle->volume_m3,
                'current_city' => $bid->vehicle->current_city,
                'is_online' => $bid->vehicle->is_online,
                'assigned_driver' => $bid->vehicle->assignedDriver ? [
                    'id' => $bid->vehicle->assignedDriver->id,
                    'name' => $bid->vehicle->assignedDriver->name,
                    'phone' => $bid->vehicle->assignedDriver->phone,
                    'email' => $bid->vehicle->assignedDriver->email,
                ] : null,
                'url' => route('vehicles.show', $bid->vehicle),
            ] : null,
            'urls' => [
                'accept' => route('bids.accept', $bid),
            ],
        ];
    }

    private function loadShowPayload(FreightLoad $load, bool $canSeeContacts, ?User $user): array
    {
        $acceptedBid = $this->acceptedContractBid($load);
        $visibleBids = $this->visibleBidsFor($load, $user);
        $canViewContract = $acceptedBid && $this->canViewContract($user, $load, $acceptedBid);
        $canSeeDeliveryConfirmation = $user
            && ($user->isAdmin()
                || $user->isDispatcher()
                || $user->id === $load->shipper_id
                || $load->bids->contains(fn ($bid) => ($bid->carrier_id === $user->id || $bid->vehicle?->assigned_driver_id === $user->id) && $bid->status === 'accepted'));
        $canCarrierUpdateDelivery = $user
            && $acceptedBid
            && $acceptedBid->canBeOperatedBy($user)
            && $load->status === 'in_progress';
        $canStaffUpdateDelivery = $user
            && $acceptedBid
            && ($user->isAdmin() || $user->isDispatcher() || $user->id === $load->shipper_id)
            && $load->status === 'in_progress';

        return [
            'id' => $load->id,
            'title' => $load->title,
            'cargo_type' => $load->cargo_type,
            'cargo_description' => $load->cargo_description,
            'loading_city' => $load->loading_city,
            'unloading_city' => $load->unloading_city,
            'body_type' => $load->body_type,
            'weight_kg' => $load->weight_kg,
            'volume_m3' => $load->volume_m3,
            'price' => $load->price,
            'payment_type' => $load->payment_type,
            'status' => $load->status,
            'loading_region' => $load->loading_region,
            'loading_address' => $load->loading_address,
            'unloading_region' => $load->unloading_region,
            'unloading_address' => $load->unloading_address,
            'loading_date' => $this->formatDate($load->loading_date),
            'unloading_date' => $this->formatDate($load->unloading_date),
            'places_count' => $load->places_count,
            'loading_type' => $load->loading_type,
            'temperature_mode' => $load->temperature_mode,
            'payment_terms' => $load->payment_terms,
            'cargo_photo_url' => $this->publicUrl($load->cargo_photo_path),
            'delivery_stage' => $load->delivery_stage,
            'contract_url' => $canViewContract ? route('loads.contract', $load) : null,
            'delivery_confirmation' => $canSeeDeliveryConfirmation ? [
                'token' => $load->delivery_confirmation_token,
                'code' => $load->delivery_confirmation_code,
                'url' => route('loads.show', ['load' => $load->id, 'confirm' => $load->delivery_confirmation_token]),
                'confirmed_at' => $this->formatDateTime($load->completion_confirmed_at),
            ] : null,
            'contact_name' => $canSeeContacts ? $load->contact_name : null,
            'contact_phone' => $canSeeContacts ? $load->contact_phone : null,
            'contact_email' => $canSeeContacts ? $load->contact_email : null,
            'company' => [
                'name' => $load->company?->name,
                'phone' => $canSeeContacts ? $load->company?->phone : null,
                'email' => $canSeeContacts ? $load->company?->email : null,
                'verification_status' => $load->company?->verification_status,
            ],
            'bids' => $visibleBids->map(fn ($bid) => [
                'id' => $bid->id,
                'carrier_id' => $bid->carrier_id,
                'status' => $bid->status,
                'comment' => $bid->comment,
                'carrier' => [
                    'name' => $bid->carrier?->name,
                    'company' => [
                        'name' => $bid->carrier?->company?->name,
                        'phone' => $bid->carrier?->company?->phone,
                        'email' => $bid->carrier?->company?->email,
                    ],
                ],
                'vehicle' => $bid->vehicle ? [
                    'id' => $bid->vehicle->id,
                    'title' => $bid->vehicle->title,
                ] : null,
                'contract_accepted_at' => $this->formatDateTime($bid->contract_accepted_at),
                'contract_signed_at' => $this->formatDateTime($bid->contract_signed_at),
                'carrier_cargo_photo_url' => $this->publicUrl($bid->carrier_cargo_photo_path),
                'can_upload_carrier_cargo_photo' => $user
                    && $bid->canBeOperatedBy($user)
                    && $bid->status === 'accepted'
                    && $load->status === 'in_progress',
            ])->values(),
            'delivery_events' => $canSeeDeliveryConfirmation
                ? $load->deliveryEvents
                    ->sortByDesc('id')
                    ->map(fn ($event) => [
                        'id' => $event->id,
                        'type' => $event->type,
                        'note' => $event->note,
                    'created_at' => $this->formatDateTime($event->created_at),
                        'actor' => [
                            'name' => $event->actor?->name,
                            'role' => $event->actor?->role,
                        ],
                    ])
                    ->values()
                : [],
            'can_update_delivery' => $canCarrierUpdateDelivery || $canStaffUpdateDelivery,
            'delivery_event_options' => $canCarrierUpdateDelivery
                ? DeliveryEvent::carrierAvailableEventTypes($load->delivery_stage)
                : ($canStaffUpdateDelivery ? DeliveryEvent::STAFF_EVENT_TYPES : []),
            'next_delivery_event' => $canCarrierUpdateDelivery
                ? DeliveryEvent::nextCarrierEvent($load->delivery_stage)
                : null,
        ];
    }

    private function storePhoto(
        Request $request,
        string $field,
        string $directory,
        FreightMediaService $media,
        ?string $previousPath = null,
    ): ?array
    {
        if (! $request->hasFile($field)) {
            return null;
        }

        return $media->storeOptimizedImage($request->file($field), $directory, $previousPath);
    }

    private function publicUrl(?string $path): ?string
    {
        return $path ? '/storage/'.ltrim($path, '/') : null;
    }

    private function visibleBidsFor(FreightLoad $load, ?User $user)
    {
        if (! $user) {
            return collect();
        }

        if ($user->isAdmin() || $user->isDispatcher() || $user->id === $load->shipper_id) {
            return $load->bids;
        }

        if (! $user->isCarrier()) {
            return collect();
        }

        return $load->bids->filter(fn ($bid) => $this->canAccessCarrierBid($user, $bid))->values();
    }

    private function canAccessCarrierBid(User $user, Bid $bid): bool
    {
        return $bid->carrier_id === $user->id
            || $bid->vehicle?->assigned_driver_id === $user->id
            || (
                $user->canManageCarrierFleet()
                && $user->activeCarrierCompany()?->id
                && $bid->company_id === $user->activeCarrierCompany()->id
            );
    }

    private function carrierVehiclesForBid(User $user, FreightLoad $load)
    {
        return Vehicle::query()
            ->eligibleForLoad($load)
            ->where(function ($query) use ($user) {
                if ($user->isCarrierCompanyDriver()) {
                    $query->where('carrier_id', $user->id)
                        ->orWhere('assigned_driver_id', $user->id);

                    return;
                }

                $query->where('carrier_id', $user->id);

                if ($companyId = $user->activeCarrierCompany()?->id) {
                    $query->orWhere('company_id', $companyId);
                }
            })
            ->orderBy('title')
            ->get(['id', 'title', 'registration_number']);
    }

    private function releaseAcceptedVehicles(FreightLoad $load): void
    {
        $load->bids
            ->where('status', 'accepted')
            ->pluck('vehicle')
            ->filter()
            ->unique('id')
            ->each(fn (Vehicle $vehicle) => $vehicle->update(['is_available' => true]));
    }

    private function formatDate($date): ?string
    {
        return $date ? $date->format('d.m.Y') : null;
    }

    private function formatDateTime($date): ?string
    {
        return $date ? $date->format('d.m.Y H:i') : null;
    }

    private function notifyAcceptedBidUsers(FreightLoad $load, string $type, string $title, string $message): void
    {
        $bid = $this->acceptedContractBid($load);

        if (! $bid) {
            return;
        }

        collect([$bid->carrier_id, $bid->vehicle?->assigned_driver_id])
            ->filter()
            ->unique()
            ->each(fn ($userId) => FreightNotification::create([
                'user_id' => $userId,
                'type' => $type,
                'title' => $title,
                'message' => $message,
                'data_json' => ['bid_id' => $bid->id, 'load_id' => $load->id, 'action' => 'delivery'],
            ]));
    }

    private function contractPayload(FreightLoad $load, Bid $bid): array
    {
        return [
            'number' => $load->id.'-'.$bid->id,
            'terms_version' => config('freight.contracts.terms_version'),
            'generated_at' => now()->format('d.m.Y H:i'),
            'shipper' => [
                'name' => $load->company?->name ?: $load->shipper?->name,
                'inn' => $load->company?->inn,
                'address' => $load->company?->legal_address,
                'contact' => $load->contact_name ?: $load->shipper?->name,
                'phone' => $load->contact_phone ?: $load->company?->phone,
                'email' => $load->contact_email ?: $load->company?->email,
            ],
            'carrier' => [
                'name' => $bid->company?->name ?: $bid->carrier?->company?->name ?: $bid->carrier?->name,
                'inn' => $bid->company?->inn,
                'address' => $bid->company?->legal_address,
                'contact' => $bid->carrier?->name,
                'phone' => $bid->carrier?->phone ?: $bid->company?->phone ?: $bid->carrier?->company?->phone,
                'email' => $bid->carrier?->email ?: $bid->company?->email ?: $bid->carrier?->company?->email,
            ],
            'load' => [
                'id' => $load->id,
                'title' => $load->title,
                'description' => $load->cargo_description ?: $load->cargo_type,
                'loading' => collect([$load->loading_city, $load->loading_region, $load->loading_address])->filter()->join(', '),
                'unloading' => collect([$load->unloading_city, $load->unloading_region, $load->unloading_address])->filter()->join(', '),
                'loading_date' => $this->formatDate($load->loading_date),
                'unloading_date' => $this->formatDate($load->unloading_date),
                'weight_kg' => $load->weight_kg,
                'volume_m3' => $load->volume_m3,
                'places_count' => $load->places_count,
                'status' => $load->status,
                'confirmation_code' => $load->delivery_confirmation_code,
                'completion_confirmed_at' => $this->formatDateTime($load->completion_confirmed_at),
            ],
            'vehicle' => [
                'title' => $bid->vehicle?->title,
                'registration_number' => $bid->vehicle?->registration_number,
                'body_type' => $bid->vehicle?->body_type ?: $load->body_type,
            ],
            'payment' => [
                'price' => $load->price,
                'currency' => $load->price_currency,
                'payment_type' => $load->payment_type,
                'payment_terms' => $load->payment_terms,
            ],
            'signatures' => [
                'carrier_accepted_at' => $this->formatDateTime($bid->contract_accepted_at),
                'shipper_signed_at' => $this->formatDateTime($bid->contract_signed_at),
            ],
        ];
    }
}
