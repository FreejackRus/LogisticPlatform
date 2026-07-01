<?php

use App\Models\Bid;
use App\Models\Complaint;
use App\Models\DeliveryEvent;
use App\Models\DispatcherConnection;
use App\Models\FreightLoad;
use App\Models\FreightNotification;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;

it('registers shippers and carriers only from the public form', function () {
    $this->post('/register', [
        'name' => 'Ivan',
        'email' => 'shipper-new@example.com',
        'phone' => '+7 900 111 22 33',
        'role' => 'shipper',
        'password' => 'password',
        'password_confirmation' => 'password',
        'agree_to_terms' => true,
        'agree_to_privacy' => true,
        'agree_to_platform_role' => true,
    ])->assertRedirect(route('freight.company.edit', absolute: false));

    expect(User::where('email', 'shipper-new@example.com')->first()->role)->toBe('shipper');

    $this->post(route('logout'));

    $this->post('/register', [
        'name' => 'Dispatcher',
        'email' => 'dispatcher-public@example.com',
        'role' => 'dispatcher',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertSessionHasErrors('role');
});

it('creates company load vehicle and map objects', function () {
    Storage::fake('public');

    $shipper = freightUser('shipper');
    $shipperCompany = freightCompany($shipper, 'shipper');
    $shipperCompany->update(['verification_status' => 'verified']);

    $this->actingAs($shipper)
        ->post(route('loads.store'), [
            'title' => 'Steel to Kazan',
            'loading_city' => 'Москва',
            'unloading_city' => 'Казань',
            'body_type' => 'tent',
            'weight_kg' => 5000,
            'price' => 62000,
            'is_urgent' => true,
            'publish' => true,
            'cargo_photo' => UploadedFile::fake()->image('cargo.jpg', 900, 600),
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $load = FreightLoad::first();
    expect($load->status)->toBe('active')
        ->and((float) $load->loading_lat)->toBe(55.7558)
        ->and($load->cargo_photo_path)->toStartWith('loads/')
        ->and($load->cargo_photo_path)->not->toContain('data:image')
        ->and($load->cargo_photo_meta['mime_type'])->toBe('image/jpeg')
        ->and($load->cargo_photo_meta['optimized'])->toBeTrue();
    expect(str_ends_with($load->cargo_photo_path, '.jpg'))->toBeTrue();
    Storage::disk('public')->assertExists($load->cargo_photo_path);

    $carrier = freightUser('carrier');
    $carrierCompany = freightCompany($carrier, 'carrier');
    $carrierCompany->update(['verification_status' => 'verified']);

    $this->actingAs($carrier)
        ->post(route('vehicles.store'), [
            'title' => 'Tilt 20t',
            'body_type' => 'tent',
            'capacity_kg' => 20000,
            'current_city' => 'Moscow',
            'is_available' => true,
            'is_location_visible' => true,
            'photo' => UploadedFile::fake()->image('vehicle.jpg', 900, 600),
        ])
        ->assertRedirect();

    $vehicle = Vehicle::first();
    expect($vehicle->photo_path)->toStartWith('vehicles/')
        ->and($vehicle->photo_path)->not->toContain('data:image')
        ->and($vehicle->photo_meta['mime_type'])->toBe('image/jpeg')
        ->and($vehicle->photo_meta['optimized'])->toBeTrue();
    expect(str_ends_with($vehicle->photo_path, '.jpg'))->toBeTrue();
    Storage::disk('public')->assertExists($vehicle->photo_path);

    $this->actingAs($shipper)
        ->get(route('loads.show', $load))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('load.cargo_photo_url', '/storage/'.$load->cargo_photo_path)
        );

    $this->get(route('vehicles.show', $vehicle))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('vehicle.photo_url', '/storage/'.$vehicle->photo_path)
        );

    $this->actingAs($carrier)
        ->postJson(route('vehicles.location.update', $vehicle), [
            'lat' => 55.75,
            'lng' => 37.61,
            'accuracy_meters' => 20,
        ])
        ->assertOk();

    $this->getJson(route('api.map.objects'))
        ->assertOk()
        ->assertJsonCount(1, 'loads')
        ->assertJsonCount(1, 'vehicles')
        ->assertJsonPath('loads.0.url', route('loads.show', $load))
        ->assertJsonPath('vehicles.0.url', route('vehicles.show', $vehicle));

    $this->getJson(route('api.map.objects', [
        'types' => ['loads'],
        'q' => 'Steel',
        'from_city' => $load->loading_city,
        'to_city' => $load->unloading_city,
        'body_type' => 'tent',
        'urgent' => 1,
        'verified' => 1,
        'min_price' => 60000,
        'max_price' => 63000,
    ]))
        ->assertOk()
        ->assertJsonCount(1, 'loads')
        ->assertJsonCount(0, 'vehicles')
        ->assertJsonPath('filters.q', 'Steel')
        ->assertJsonPath('filters.urgent', true)
        ->assertJsonPath('filters.verified', true)
        ->assertJsonPath('filters.min_price', 60000)
        ->assertJsonPath('filters.max_price', 63000);

    $this->getJson(route('api.map.objects', [
        'types' => ['vehicles'],
        'from_city' => 'Moscow',
        'body_type' => 'tent',
        'online' => 1,
        'verified' => 1,
    ]))
        ->assertOk()
        ->assertJsonCount(0, 'loads')
        ->assertJsonCount(1, 'vehicles')
        ->assertJsonPath('filters.from_city', 'Moscow')
        ->assertJsonPath('filters.body_type', 'tent');

    $this->getJson(route('api.map.objects', [
        'types' => ['vehicles'],
        'online' => 1,
        'bounds' => [
            'north' => 56,
            'south' => 55,
            'east' => 38,
            'west' => 37,
        ],
    ]))
        ->assertOk()
        ->assertJsonCount(0, 'loads')
        ->assertJsonCount(1, 'vehicles')
        ->assertJsonPath('filters.bounded', true)
        ->assertJsonPath('filters.online', true);

    $otherCarrier = freightUser('carrier', ['email' => 'map-other-carrier@example.com']);
    $otherVehicle = Vehicle::create([
        'carrier_id' => $otherCarrier->id,
        'title' => 'Other carrier truck',
        'current_lat' => 55.76,
        'current_lng' => 37.62,
        'is_available' => true,
        'is_online' => true,
        'is_location_visible' => true,
    ]);

    $this->actingAs($carrier)
        ->get(route('vehicles.show', $vehicle))
        ->assertOk();

    $this->actingAs($carrier)
        ->get(route('vehicles.show', $otherVehicle))
        ->assertForbidden();

    $this->actingAs($carrier)
        ->getJson(route('api.map.objects', ['types' => ['vehicles']]))
        ->assertOk()
        ->assertJsonCount(1, 'vehicles')
        ->assertJsonPath('vehicles.0.id', $vehicle->id);

    $this->getJson(route('api.map.objects', [
        'bounds' => [
            'north' => 60,
            'south' => 59,
            'east' => 31,
            'west' => 30,
        ],
    ]))
        ->assertOk()
        ->assertJsonCount(0, 'loads')
        ->assertJsonCount(0, 'vehicles');

    $this->actingAs($shipper)
        ->put(route('loads.update', $load), [
            'title' => 'Updated fixed price load',
            'loading_city' => 'Москва',
            'unloading_city' => 'Казань',
            'weight_kg' => 7000,
            'price' => 71000,
            'payment_type' => 'bank_transfer',
        ])
        ->assertRedirect(route('loads.show', $load));

    expect($load->refresh()->price)->toBe(71000)
        ->and($load->title)->toBe('Updated fixed price load');

    $this->actingAs($carrier)
        ->put(route('vehicles.update', $vehicle), [
            'title' => 'Updated tilt 20t',
            'body_type' => 'tent',
            'capacity_kg' => 22000,
            'is_available' => true,
            'is_location_visible' => true,
        ])
        ->assertRedirect();

    expect($vehicle->refresh()->title)->toBe('Updated tilt 20t')
        ->and($vehicle->capacity_kg)->toBe(22000);
});

it('filters the public load catalog like classifieds', function () {
    $shipper = freightUser('shipper');
    $company = freightCompany($shipper, 'shipper');

    $matchingLoad = FreightLoad::create([
        'shipper_id' => $shipper->id,
        'company_id' => $company->id,
        'title' => 'Металл на Казань',
        'cargo_type' => 'металл',
        'loading_city' => 'Москва',
        'unloading_city' => 'Казань',
        'body_type' => 'тент',
        'payment_type' => 'bank_transfer',
        'price' => 65000,
        'status' => 'active',
        'is_urgent' => true,
        'published_at' => now(),
    ]);

    FreightLoad::create([
        'shipper_id' => $shipper->id,
        'company_id' => $company->id,
        'title' => 'Продукты на Уфу',
        'cargo_type' => 'продукты',
        'loading_city' => 'Самара',
        'unloading_city' => 'Уфа',
        'body_type' => 'рефрижератор',
        'payment_type' => 'cash',
        'price' => 95000,
        'status' => 'active',
        'published_at' => now(),
    ]);

    FreightLoad::create([
        'shipper_id' => $shipper->id,
        'company_id' => $company->id,
        'title' => 'Taken load should stay hidden',
        'cargo_type' => 'metal',
        'loading_city' => 'Moscow',
        'unloading_city' => 'Kazan',
        'body_type' => 'tent',
        'payment_type' => 'bank_transfer',
        'price' => 64000,
        'status' => 'in_progress',
        'published_at' => null,
    ]);

    $this->get(route('loads.index', [
        'q' => 'металл',
        'from_city' => 'Моск',
        'to_city' => 'Каз',
        'body_type' => 'тент',
        'payment_type' => 'bank_transfer',
        'min_price' => 60000,
        'max_price' => 70000,
        'urgent' => 1,
    ]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Freight/Loads/Index')
            ->where('loads.data.0.id', $matchingLoad->id)
            ->has('loads.data', 1)
            ->where('filters.q', 'металл')
            ->has('filterOptions.bodyTypes', 2)
            ->where('stats.total', 2)
        );
});

it('filters the public carrier catalog', function () {
    $carrier = freightUser('carrier');
    $company = freightCompany($carrier, 'carrier');
    $shipper = freightUser('shipper');

    $matchingVehicle = Vehicle::create([
        'carrier_id' => $carrier->id,
        'company_id' => $company->id,
        'title' => 'Тент 20 тонн',
        'vehicle_type' => 'truck',
        'body_type' => 'тент',
        'capacity_kg' => 20000,
        'volume_m3' => 82,
        'current_city' => 'Москва',
        'is_available' => true,
        'is_online' => true,
        'is_location_visible' => true,
        'last_location_at' => now(),
    ]);

    Vehicle::create([
        'carrier_id' => $carrier->id,
        'company_id' => $company->id,
        'title' => 'Рефрижератор',
        'vehicle_type' => 'truck',
        'body_type' => 'рефрижератор',
        'capacity_kg' => 5000,
        'volume_m3' => 30,
        'current_city' => 'Казань',
        'is_available' => true,
        'is_online' => false,
        'is_location_visible' => true,
    ]);

    $this->actingAs($shipper)
        ->get(route('vehicles.index', [
        'q' => 'тент',
        'city' => 'Моск',
        'body_type' => 'тент',
        'min_capacity' => 15000,
        'min_volume' => 60,
        'online' => 1,
    ]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Freight/Vehicles/Catalog')
            ->where('vehicles.data.0.id', $matchingVehicle->id)
            ->has('vehicles.data', 1)
            ->where('filters.q', 'тент')
            ->has('filterOptions.bodyTypes', 2)
        );

    $this->actingAs($carrier)
        ->get(route('vehicles.index'))
        ->assertRedirect(route('vehicles.mine'));
});

it('separates carrier fleet managers from company drivers', function () {
    $owner = freightUser('carrier', ['email' => 'fleet-owner@example.com']);
    $manager = freightUser('carrier', ['email' => 'fleet-manager@example.com']);
    $driver = freightUser('carrier', ['email' => 'fleet-driver@example.com']);
    $otherCarrier = freightUser('carrier', ['email' => 'outside-carrier@example.com']);
    $freeManager = freightUser('carrier', ['email' => 'fleet-free-manager@example.com']);
    $blockedCarrier = freightUser('carrier', ['email' => 'fleet-blocked@example.com', 'is_blocked' => true]);
    $otherCompanyOwner = freightUser('carrier', ['email' => 'fleet-other-owner@example.com']);
    $occupiedCarrier = freightUser('carrier', ['email' => 'fleet-occupied@example.com']);

    $company = freightCompany($owner, 'carrier');
    $company->update([
        'carrier_profile_type' => 'company',
        'allows_carrier_members' => true,
    ]);
    $otherCompany = freightCompany($otherCompanyOwner, 'carrier');
    $otherCompany->update([
        'carrier_profile_type' => 'company',
        'allows_carrier_members' => true,
    ]);
    $otherCompany->carrierMembers()->syncWithoutDetaching([
        $occupiedCarrier->id => ['role' => 'driver', 'status' => 'active', 'joined_at' => now()],
    ]);

    $company->carrierMembers()->syncWithoutDetaching([
        $manager->id => ['role' => 'manager', 'status' => 'active', 'joined_at' => now()],
        $driver->id => ['role' => 'driver', 'status' => 'active', 'joined_at' => now()],
    ]);

    $companyVehicle = Vehicle::create([
        'carrier_id' => $owner->id,
        'assigned_driver_id' => $driver->id,
        'company_id' => $company->id,
        'title' => 'Company truck',
        'is_available' => true,
        'is_location_visible' => true,
    ]);

    Vehicle::create([
        'carrier_id' => $otherCarrier->id,
        'title' => 'Outside truck',
        'is_available' => true,
        'is_location_visible' => true,
    ]);

    $this->actingAs($manager)
        ->get(route('vehicles.mine'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Freight/Vehicles/Index')
            ->where('canManageFleet', true)
            ->where('isDriverWorkspace', false)
            ->where('activeCarrierCompany.id', $company->id)
            ->where('vehicles.0.id', $companyVehicle->id)
            ->has('vehicles', 1)
        );

    $this->actingAs($driver)
        ->get(route('vehicles.mine'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Freight/Vehicles/Index')
            ->where('canManageFleet', false)
            ->where('isDriverWorkspace', true)
            ->where('vehicles.0.id', $companyVehicle->id)
            ->has('vehicles', 1)
        );

    $this->actingAs($driver)
        ->post(route('vehicles.store'), [
            'title' => 'Driver should not create fleet vehicle',
            'is_available' => true,
            'is_location_visible' => true,
        ])
        ->assertForbidden();

    $this->actingAs($driver)
        ->get(route('loads.index'))
        ->assertRedirect(route('carrier.deliveries.index'));

    $this->actingAs($driver)
        ->get(route('freight.company.edit'))
        ->assertForbidden();

    $this->actingAs($driver)
        ->post(route('freight.company.update'), [
            'name' => 'Driver company profile',
        ])
        ->assertForbidden();

    $this->actingAs($driver)
        ->post(route('freight.company.carriers.store'), [
            'email' => $manager->email,
            'role' => 'manager',
        ])
        ->assertForbidden();

    $this->actingAs($owner)
        ->post(route('freight.company.carriers.store'), [
            'email' => $freeManager->email,
            'role' => 'manager',
        ])
        ->assertRedirect();

    expect($company->carrierMembers()
        ->where('users.id', $freeManager->id)
        ->wherePivot('role', 'manager')
        ->wherePivot('status', 'active')
        ->exists())->toBeTrue()
        ->and(FreightNotification::query()
            ->where('user_id', $freeManager->id)
            ->where('type', 'carrier_company_member_added')
            ->where('data_json->company_id', $company->id)
            ->exists())->toBeTrue();

    $this->actingAs($owner)
        ->post(route('freight.company.carriers.store'), [
            'email' => $blockedCarrier->email,
            'role' => 'driver',
        ])
        ->assertSessionHasErrors('email');

    $this->actingAs($owner)
        ->post(route('freight.company.carriers.store'), [
            'email' => $otherCompanyOwner->email,
            'role' => 'manager',
        ])
        ->assertSessionHasErrors('email');

    $this->actingAs($owner)
        ->post(route('freight.company.carriers.store'), [
            'email' => $occupiedCarrier->email,
            'role' => 'driver',
        ])
        ->assertSessionHasErrors('email');
});

it('prevents admins from marking vehicles with active deliveries as available', function () {
    $admin = freightUser('admin', ['email' => 'admin-active-vehicle@example.com']);
    $shipper = freightUser('shipper', ['email' => 'admin-active-shipper@example.com']);
    $shipperCompany = freightCompany($shipper, 'shipper');
    $carrier = freightUser('carrier', ['email' => 'admin-active-carrier@example.com']);
    $carrierCompany = freightCompany($carrier, 'carrier');

    $load = FreightLoad::create([
        'shipper_id' => $shipper->id,
        'company_id' => $shipperCompany->id,
        'title' => 'Active admin vehicle load',
        'loading_city' => 'Moscow',
        'unloading_city' => 'Kazan',
        'status' => 'in_progress',
        'delivery_stage' => 'carrier_selected',
    ]);
    $vehicle = Vehicle::create([
        'carrier_id' => $carrier->id,
        'company_id' => $carrierCompany->id,
        'title' => 'Busy admin truck',
        'is_available' => false,
        'is_location_visible' => true,
    ]);

    Bid::create([
        'load_id' => $load->id,
        'carrier_id' => $carrier->id,
        'company_id' => $carrierCompany->id,
        'vehicle_id' => $vehicle->id,
        'status' => 'accepted',
        'accepted_at' => now(),
        'contract_accepted_at' => now(),
        'contract_signed_at' => now(),
    ]);

    $this->actingAs($admin)
        ->patch(route('admin.freight.vehicles.update', $vehicle), [
            'is_available' => true,
        ])
        ->assertSessionHasErrors('is_available');

    expect($vehicle->refresh()->is_available)->toBeFalse();

    $this->actingAs($admin)
        ->patch(route('admin.freight.vehicles.update', $vehicle), [
            'is_location_visible' => false,
            'current_city' => 'Kazan',
        ])
        ->assertRedirect();

    expect($vehicle->refresh()->is_available)->toBeFalse()
        ->and($vehicle->is_location_visible)->toBeFalse()
        ->and($vehicle->current_city)->toBe('Kazan');
});

it('keeps admin load moderation inside the delivery workflow', function () {
    $admin = freightUser('admin', ['email' => 'admin-load-workflow@example.com']);
    $shipper = freightUser('shipper', ['email' => 'admin-load-shipper@example.com']);
    $shipperCompany = freightCompany($shipper, 'shipper');
    $carrier = freightUser('carrier', ['email' => 'admin-load-carrier@example.com']);
    $carrierManager = freightUser('carrier', ['email' => 'admin-load-carrier-manager@example.com']);
    $carrierCompany = freightCompany($carrier, 'carrier');
    $carrierCompany->update([
        'carrier_profile_type' => 'company',
        'allows_carrier_members' => true,
    ]);
    $carrierCompany->carrierMembers()->syncWithoutDetaching([
        $carrierManager->id => ['role' => 'manager', 'status' => 'active', 'joined_at' => now()],
    ]);

    $draftLoad = FreightLoad::create([
        'shipper_id' => $shipper->id,
        'company_id' => $shipperCompany->id,
        'title' => 'Admin draft load',
        'loading_city' => 'Moscow',
        'unloading_city' => 'Kazan',
        'status' => 'draft',
    ]);

    $this->actingAs($admin)
        ->patch(route('admin.freight.loads.update', $draftLoad), [
            'status' => 'in_progress',
        ])
        ->assertSessionHasErrors('status');

    $this->actingAs($admin)
        ->patch(route('admin.freight.loads.update', $draftLoad), [
            'status' => 'completed',
        ])
        ->assertSessionHasErrors('status');

    $activeLoad = FreightLoad::create([
        'shipper_id' => $shipper->id,
        'company_id' => $shipperCompany->id,
        'title' => 'Admin active load',
        'loading_city' => 'Moscow',
        'unloading_city' => 'Samara',
        'status' => 'active',
    ]);

    $this->actingAs($admin)
        ->patch(route('admin.freight.loads.update', $activeLoad), [
            'status' => 'archived',
        ])
        ->assertRedirect();

    expect($activeLoad->refresh()->status)->toBe('archived');

    $adminCancelledLoad = FreightLoad::create([
        'shipper_id' => $shipper->id,
        'company_id' => $shipperCompany->id,
        'title' => 'Admin cancelled active load',
        'loading_city' => 'Moscow',
        'unloading_city' => 'Kazan',
        'status' => 'active',
        'bids_count' => 1,
    ]);
    $adminCancelledVehicle = Vehicle::create([
        'carrier_id' => $carrier->id,
        'company_id' => $carrierCompany->id,
        'title' => 'Admin cancelled pending truck',
        'is_available' => true,
    ]);
    $adminCancelledBid = Bid::create([
        'load_id' => $adminCancelledLoad->id,
        'carrier_id' => $carrier->id,
        'company_id' => $carrierCompany->id,
        'vehicle_id' => $adminCancelledVehicle->id,
        'status' => 'pending',
        'contract_accepted_at' => now(),
    ]);

    $this->actingAs($admin)
        ->patch(route('admin.freight.loads.update', $adminCancelledLoad), [
            'status' => 'cancelled',
        ])
        ->assertRedirect();

    expect($adminCancelledLoad->refresh()->status)->toBe('cancelled')
        ->and($adminCancelledLoad->bids_count)->toBe(0)
        ->and($adminCancelledBid->refresh()->status)->toBe('cancelled')
        ->and(FreightNotification::query()
            ->where('user_id', $carrier->id)
            ->where('type', 'load_cancelled')
            ->where('data_json->bid_id', $adminCancelledBid->id)
            ->exists())->toBeTrue()
        ->and(FreightNotification::query()
            ->where('user_id', $carrierManager->id)
            ->where('type', 'load_cancelled')
            ->where('data_json->bid_id', $adminCancelledBid->id)
            ->exists())->toBeTrue();

    $earlyLoad = FreightLoad::create([
        'shipper_id' => $shipper->id,
        'company_id' => $shipperCompany->id,
        'title' => 'Admin early delivery',
        'loading_city' => 'Moscow',
        'unloading_city' => 'Kazan',
        'status' => 'in_progress',
        'delivery_stage' => 'carrier_selected',
    ]);

    $earlyVehicle = Vehicle::create([
        'carrier_id' => $carrier->id,
        'company_id' => $carrierCompany->id,
        'title' => 'Early delivery truck',
        'is_available' => false,
    ]);

    Bid::create([
        'load_id' => $earlyLoad->id,
        'carrier_id' => $carrier->id,
        'company_id' => $carrierCompany->id,
        'vehicle_id' => $earlyVehicle->id,
        'status' => 'accepted',
        'accepted_at' => now(),
        'contract_accepted_at' => now(),
        'contract_signed_at' => now(),
    ]);

    $this->actingAs($admin)
        ->patch(route('admin.freight.loads.update', $earlyLoad), [
            'status' => 'cancelled',
        ])
        ->assertRedirect();

    expect($earlyLoad->refresh()->status)->toBe('cancelled')
        ->and($earlyVehicle->refresh()->is_available)->toBeTrue();

    $loadedLoad = FreightLoad::create([
        'shipper_id' => $shipper->id,
        'company_id' => $shipperCompany->id,
        'title' => 'Admin loaded delivery',
        'loading_city' => 'Moscow',
        'unloading_city' => 'Kazan',
        'status' => 'in_progress',
        'delivery_stage' => 'loaded',
    ]);

    $this->actingAs($admin)
        ->patch(route('admin.freight.loads.update', $loadedLoad), [
            'status' => 'cancelled',
        ])
        ->assertSessionHasErrors('status');
});

it('allows carrier fleet roles to build accepted load route only for their delivery', function () {
    Http::fake([
        'router.project-osrm.org/route/v1/driving/*' => Http::response([
            'routes' => [[
                'distance' => 9100,
                'duration' => 1400,
                'geometry' => [
                    'coordinates' => [
                        [37.5000, 55.7000],
                        [37.6173, 55.7558],
                    ],
                ],
            ]],
        ]),
    ]);

    $shipper = freightUser('shipper', ['email' => 'fleet-route-shipper@example.com']);
    $shipperCompany = freightCompany($shipper, 'shipper');
    $owner = freightUser('carrier', ['email' => 'fleet-route-owner@example.com']);
    $manager = freightUser('carrier', ['email' => 'fleet-route-manager@example.com']);
    $driver = freightUser('carrier', ['email' => 'fleet-route-driver@example.com']);
    $outsideCarrier = freightUser('carrier', ['email' => 'fleet-route-outside@example.com']);

    $company = freightCompany($owner, 'carrier');
    $company->update([
        'carrier_profile_type' => 'company',
        'allows_carrier_members' => true,
    ]);
    $company->carrierMembers()->syncWithoutDetaching([
        $manager->id => ['role' => 'manager', 'status' => 'active', 'joined_at' => now()],
        $driver->id => ['role' => 'driver', 'status' => 'active', 'joined_at' => now()],
    ]);

    $load = FreightLoad::create([
        'shipper_id' => $shipper->id,
        'company_id' => $shipperCompany->id,
        'title' => 'Fleet route load',
        'loading_city' => 'Moscow',
        'unloading_city' => 'Kazan',
        'loading_lat' => 55.7558,
        'loading_lng' => 37.6173,
        'status' => 'in_progress',
        'delivery_stage' => 'carrier_selected',
    ]);

    FreightLoad::create([
        'shipper_id' => $shipper->id,
        'company_id' => $shipperCompany->id,
        'title' => 'Open market route load',
        'loading_city' => 'Moscow',
        'unloading_city' => 'Tver',
        'loading_lat' => 55.8000,
        'loading_lng' => 37.7000,
        'status' => 'active',
        'published_at' => now(),
    ]);

    $vehicle = Vehicle::create([
        'carrier_id' => $owner->id,
        'assigned_driver_id' => $driver->id,
        'company_id' => $company->id,
        'title' => 'Fleet route truck',
        'current_lat' => 55.7000,
        'current_lng' => 37.5000,
        'is_available' => false,
        'is_location_visible' => true,
    ]);

    Bid::create([
        'load_id' => $load->id,
        'carrier_id' => $owner->id,
        'company_id' => $company->id,
        'vehicle_id' => $vehicle->id,
        'status' => 'accepted',
        'accepted_at' => now(),
        'contract_accepted_at' => now(),
        'contract_signed_at' => now(),
    ]);

    foreach ([$owner, $manager, $driver] as $user) {
        $this->actingAs($user)
            ->getJson(route('api.map.accepted-route', $load))
            ->assertOk()
            ->assertJsonPath('distance_m', 9100)
            ->assertJsonPath('vehicle.id', $vehicle->id);
    }

    $this->actingAs($driver)
        ->getJson(route('api.map.objects', ['types' => ['loads']]))
        ->assertOk()
        ->assertJsonCount(1, 'loads')
        ->assertJsonPath('loads.0.id', $load->id);

    $this->actingAs($outsideCarrier)
        ->getJson(route('api.map.accepted-route', $load))
        ->assertForbidden();
});

it('limits delivery operations to the assigned driver when a fleet vehicle has one', function () {
    Storage::fake('public');

    $shipper = freightUser('shipper', ['email' => 'driver-ops-shipper@example.com']);
    $shipperCompany = freightCompany($shipper, 'shipper');
    $owner = freightUser('carrier', ['email' => 'driver-ops-owner@example.com']);
    $manager = freightUser('carrier', ['email' => 'driver-ops-manager@example.com']);
    $driver = freightUser('carrier', ['email' => 'driver-ops-driver@example.com']);

    $company = freightCompany($owner, 'carrier');
    $company->update([
        'carrier_profile_type' => 'company',
        'allows_carrier_members' => true,
    ]);
    $company->carrierMembers()->syncWithoutDetaching([
        $manager->id => ['role' => 'manager', 'status' => 'active', 'joined_at' => now()],
        $driver->id => ['role' => 'driver', 'status' => 'active', 'joined_at' => now()],
    ]);

    $load = FreightLoad::create([
        'shipper_id' => $shipper->id,
        'company_id' => $shipperCompany->id,
        'title' => 'Driver operated load',
        'loading_city' => 'Moscow',
        'unloading_city' => 'Kazan',
        'delivery_confirmation_token' => 'driver-ops-token',
        'delivery_confirmation_code' => '111222',
        'status' => 'in_progress',
        'delivery_stage' => 'carrier_selected',
    ]);

    $vehicle = Vehicle::create([
        'carrier_id' => $owner->id,
        'assigned_driver_id' => $driver->id,
        'company_id' => $company->id,
        'title' => 'Driver operated truck',
        'is_available' => false,
    ]);

    $bid = Bid::create([
        'load_id' => $load->id,
        'carrier_id' => $owner->id,
        'company_id' => $company->id,
        'vehicle_id' => $vehicle->id,
        'status' => 'accepted',
        'accepted_at' => now(),
        'contract_accepted_at' => now(),
        'contract_signed_at' => now(),
    ]);

    foreach ([$owner, $manager] as $observer) {
        $this->actingAs($observer)
            ->get(route('carrier.deliveries.show', $bid))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('delivery.can_update_delivery', false)
                ->where('delivery.can_upload_carrier_cargo_photo', false)
                ->has('delivery.delivery_event_options', 0)
            );

        $this->actingAs($observer)
            ->post(route('loads.delivery-events.store', $load), [
                'type' => 'en_route_to_pickup',
            ])
            ->assertForbidden();

        $this->actingAs($observer)
            ->post(route('bids.carrier-photo', $bid), [
                'carrier_cargo_photo' => UploadedFile::fake()->image('observer-photo.jpg', 900, 600),
            ])
            ->assertForbidden();
    }

    $this->actingAs($driver)
        ->get(route('carrier.deliveries.show', $bid))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('delivery.can_update_delivery', true)
            ->where('delivery.can_upload_carrier_cargo_photo', true)
            ->where('delivery.delivery_event_options.0', 'en_route_to_pickup')
        );

    $this->actingAs($driver)
        ->post(route('loads.delivery-events.store', $load), [
            'type' => 'en_route_to_pickup',
        ])
        ->assertRedirect();

    $this->actingAs($driver)
        ->post(route('bids.carrier-photo', $bid), [
            'carrier_cargo_photo' => UploadedFile::fake()->image('driver-photo.jpg', 900, 600),
        ])
        ->assertRedirect();

    expect($load->refresh()->delivery_stage)->toBe('en_route_to_pickup')
        ->and($bid->refresh()->carrier_cargo_photo_path)->toStartWith('bid-cargo/');
});

it('shows carrier bid workspace by fleet role', function () {
    $shipper = freightUser('shipper', ['email' => 'bid-shipper@example.com']);
    $shipperCompany = freightCompany($shipper, 'shipper');
    $owner = freightUser('carrier', ['email' => 'bid-owner@example.com']);
    $manager = freightUser('carrier', ['email' => 'bid-manager@example.com']);
    $driver = freightUser('carrier', ['email' => 'bid-driver@example.com']);
    $outsideCarrier = freightUser('carrier', ['email' => 'bid-outside@example.com']);

    $company = freightCompany($owner, 'carrier');
    $company->update([
        'carrier_profile_type' => 'company',
        'allows_carrier_members' => true,
    ]);
    $company->carrierMembers()->syncWithoutDetaching([
        $manager->id => ['role' => 'manager', 'status' => 'active', 'joined_at' => now()],
        $driver->id => ['role' => 'driver', 'status' => 'active', 'joined_at' => now()],
    ]);

    $load = FreightLoad::create([
        'shipper_id' => $shipper->id,
        'company_id' => $shipperCompany->id,
        'title' => 'Bid workspace load',
        'loading_city' => 'Moscow',
        'unloading_city' => 'Kazan',
        'status' => 'active',
        'price' => 80000,
    ]);

    $vehicle = Vehicle::create([
        'carrier_id' => $owner->id,
        'assigned_driver_id' => $driver->id,
        'company_id' => $company->id,
        'title' => 'Bid workspace truck',
        'is_available' => true,
        'is_location_visible' => true,
    ]);

    $companyBid = Bid::create([
        'load_id' => $load->id,
        'carrier_id' => $owner->id,
        'company_id' => $company->id,
        'vehicle_id' => $vehicle->id,
        'status' => 'pending',
        'contract_accepted_at' => now(),
    ]);

    Bid::create([
        'load_id' => $load->id,
        'carrier_id' => $outsideCarrier->id,
        'status' => 'pending',
        'contract_accepted_at' => now(),
    ]);

    $this->actingAs($manager)
        ->get(route('bids.mine'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Freight/Carrier/Bids')
            ->where('bids.data.0.id', $companyBid->id)
            ->where('bids.data.0.can_cancel', true)
            ->where('bids.data.0.workflow.state', 'waiting_shipper')
            ->where('bids.data.0.workflow.action_url', route('loads.show', $load))
            ->has('bids.data', 1)
            ->where('statusCounts.pending', 1)
        );

    $this->actingAs($manager)
        ->post(route('bids.store', $load), [
            'vehicle_id' => $vehicle->id,
            'comment' => 'Duplicate company response.',
            'contract_accepted' => true,
        ])
        ->assertStatus(422);

    $this->actingAs($driver)
        ->get(route('bids.mine', ['status' => 'pending']))
        ->assertRedirect(route('carrier.deliveries.index'));

    $this->actingAs($driver)
        ->get(route('loads.show', $load))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('canBid', false)
        );

    $this->actingAs($driver)
        ->post(route('bids.store', $load), [
            'vehicle_id' => $vehicle->id,
            'comment' => 'Driver should not respond for the company.',
            'contract_accepted' => true,
        ])
        ->assertForbidden();

    $this->actingAs($driver)
        ->patch(route('bids.cancel', $companyBid))
        ->assertForbidden();

    $this->actingAs($manager)
        ->patch(route('bids.cancel', $companyBid))
        ->assertRedirect();

    expect($companyBid->refresh()->status)->toBe('cancelled');
});

it('notifies carrier company stakeholders when a company bid is accepted', function () {
    $shipper = freightUser('shipper', ['email' => 'company-accepted-shipper@example.com']);
    $shipperCompany = freightCompany($shipper, 'shipper');
    $owner = freightUser('carrier', ['email' => 'company-accepted-owner@example.com']);
    $manager = freightUser('carrier', ['email' => 'company-accepted-manager@example.com']);
    $driver = freightUser('carrier', ['email' => 'company-accepted-driver@example.com']);

    $company = freightCompany($owner, 'carrier');
    $company->update([
        'carrier_profile_type' => 'company',
        'allows_carrier_members' => true,
    ]);
    $company->carrierMembers()->syncWithoutDetaching([
        $manager->id => ['role' => 'manager', 'status' => 'active', 'joined_at' => now()],
        $driver->id => ['role' => 'driver', 'status' => 'active', 'joined_at' => now()],
    ]);

    $load = FreightLoad::create([
        'shipper_id' => $shipper->id,
        'company_id' => $shipperCompany->id,
        'title' => 'Company accepted load',
        'loading_city' => 'Moscow',
        'unloading_city' => 'Kazan',
        'status' => 'active',
        'bids_count' => 1,
    ]);

    $vehicle = Vehicle::create([
        'carrier_id' => $owner->id,
        'assigned_driver_id' => $driver->id,
        'company_id' => $company->id,
        'title' => 'Company accepted truck',
        'is_available' => true,
    ]);

    $bid = Bid::create([
        'load_id' => $load->id,
        'carrier_id' => $manager->id,
        'company_id' => $company->id,
        'vehicle_id' => $vehicle->id,
        'status' => 'pending',
        'contract_accepted_at' => now(),
    ]);

    $this->actingAs($shipper)
        ->patch(route('bids.accept', $bid))
        ->assertRedirect();

    foreach ([$owner, $manager, $driver] as $recipient) {
        expect(FreightNotification::query()
            ->where('user_id', $recipient->id)
            ->where('type', 'bid_accepted')
            ->where('data_json->bid_id', $bid->id)
            ->count())->toBe(1);
    }

    $this->actingAs($owner)
        ->get(route('notifications.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('notifications.data.0.type', 'bid_accepted')
            ->where('notifications.data.0.action_url', route('carrier.deliveries.show', $bid))
            ->where('notifications.data.0.action_label', 'Открыть рейс')
        );

    expect($load->refresh()->status)->toBe('in_progress')
        ->and($vehicle->refresh()->is_available)->toBeFalse();
});

it('shows shipper bid workspace and accepts a candidate', function () {
    $shipper = freightUser('shipper', ['email' => 'candidate-shipper@example.com']);
    $otherShipper = freightUser('shipper', ['email' => 'candidate-other-shipper@example.com']);
    $shipperCompany = freightCompany($shipper, 'shipper');
    $carrier = freightUser('carrier', ['email' => 'candidate-carrier@example.com']);
    $carrierCompany = freightCompany($carrier, 'carrier');

    $load = FreightLoad::create([
        'shipper_id' => $shipper->id,
        'company_id' => $shipperCompany->id,
        'title' => 'Candidate load',
        'loading_city' => 'Moscow',
        'unloading_city' => 'Kazan',
        'status' => 'active',
        'price' => 91000,
        'bids_count' => 1,
    ]);

    $vehicle = Vehicle::create([
        'carrier_id' => $carrier->id,
        'company_id' => $carrierCompany->id,
        'title' => 'Candidate truck',
        'body_type' => 'tent',
        'capacity_kg' => 20000,
        'is_available' => true,
        'is_location_visible' => true,
    ]);

    $bid = Bid::create([
        'load_id' => $load->id,
        'carrier_id' => $carrier->id,
        'company_id' => $carrierCompany->id,
        'vehicle_id' => $vehicle->id,
        'status' => 'pending',
        'comment' => 'Ready to load tomorrow.',
        'contract_accepted_at' => now(),
    ]);

    $this->actingAs($shipper)
        ->get(route('loads.mine'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Freight/Loads/Mine')
            ->where('loads.data.0.id', $load->id)
            ->where('loads.data.0.workflow.state', 'review_bids')
            ->where('loads.data.0.workflow.action_url', route('loads.bids', $load))
        );

    $this->actingAs($otherShipper)
        ->get(route('loads.bids', $load))
        ->assertForbidden();

    $this->actingAs($shipper)
        ->get(route('loads.bids', $load))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Freight/Loads/Bids')
            ->where('load.id', $load->id)
            ->where('bids.0.id', $bid->id)
            ->where('bids.0.can_accept', true)
            ->where('canAcceptBids', true)
        );

    $this->actingAs($shipper)
        ->patch(route('bids.accept', $bid))
        ->assertRedirect();

    expect($bid->refresh()->status)->toBe('accepted')
        ->and($load->refresh()->status)->toBe('in_progress');
});

it('cancels pending bids when a shipper cancels an active load', function () {
    $shipper = freightUser('shipper', ['email' => 'cancel-load-shipper@example.com']);
    $shipperCompany = freightCompany($shipper, 'shipper');
    $carrier = freightUser('carrier', ['email' => 'cancel-load-carrier@example.com']);
    $manager = freightUser('carrier', ['email' => 'cancel-load-manager@example.com']);
    $driver = freightUser('carrier', ['email' => 'cancel-load-driver@example.com']);
    $carrierCompany = freightCompany($carrier, 'carrier');
    $carrierCompany->update([
        'carrier_profile_type' => 'company',
        'allows_carrier_members' => true,
    ]);
    $carrierCompany->carrierMembers()->syncWithoutDetaching([
        $manager->id => ['role' => 'manager', 'status' => 'active', 'joined_at' => now()],
        $driver->id => ['role' => 'driver', 'status' => 'active', 'joined_at' => now()],
    ]);

    $load = FreightLoad::create([
        'shipper_id' => $shipper->id,
        'company_id' => $shipperCompany->id,
        'title' => 'Cancelled active load',
        'loading_city' => 'Moscow',
        'unloading_city' => 'Kazan',
        'status' => 'active',
        'bids_count' => 1,
    ]);

    $vehicle = Vehicle::create([
        'carrier_id' => $carrier->id,
        'assigned_driver_id' => $driver->id,
        'company_id' => $carrierCompany->id,
        'title' => 'Cancel pending bid truck',
        'is_available' => true,
    ]);

    $bid = Bid::create([
        'load_id' => $load->id,
        'carrier_id' => $carrier->id,
        'company_id' => $carrierCompany->id,
        'vehicle_id' => $vehicle->id,
        'status' => 'pending',
        'contract_accepted_at' => now(),
    ]);

    $this->actingAs($shipper)
        ->patch(route('loads.cancel', $load))
        ->assertRedirect();

    expect($load->refresh()->status)->toBe('cancelled')
        ->and($load->bids_count)->toBe(0)
        ->and($bid->refresh()->status)->toBe('cancelled')
        ->and($bid->cancelled_at)->not->toBeNull()
        ->and(FreightNotification::query()
            ->where('user_id', $carrier->id)
            ->where('type', 'load_cancelled')
            ->where('data_json->bid_id', $bid->id)
            ->exists())->toBeTrue()
        ->and(FreightNotification::query()
            ->where('user_id', $driver->id)
            ->where('type', 'load_cancelled')
            ->where('data_json->bid_id', $bid->id)
            ->exists())->toBeTrue()
        ->and(FreightNotification::query()
            ->where('user_id', $manager->id)
            ->where('type', 'load_cancelled')
            ->where('data_json->bid_id', $bid->id)
            ->exists())->toBeTrue();

    $this->actingAs($carrier)
        ->get(route('bids.mine', ['status' => 'cancelled']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('bids.data.0.id', $bid->id)
            ->where('bids.data.0.status', 'cancelled')
            ->where('statusCounts.cancelled', 1)
        );
});

it('routes accepted carrier cancellation notifications to the bid workspace', function () {
    $shipper = freightUser('shipper', ['email' => 'accepted-cancel-shipper@example.com']);
    $shipperCompany = freightCompany($shipper, 'shipper');
    $carrier = freightUser('carrier', ['email' => 'accepted-cancel-carrier@example.com']);
    $driver = freightUser('carrier', ['email' => 'accepted-cancel-driver@example.com']);
    $carrierCompany = freightCompany($carrier, 'carrier');

    $load = FreightLoad::create([
        'shipper_id' => $shipper->id,
        'company_id' => $shipperCompany->id,
        'title' => 'Accepted cancellation load',
        'loading_city' => 'Moscow',
        'unloading_city' => 'Kazan',
        'status' => 'in_progress',
        'delivery_stage' => 'carrier_selected',
        'bids_count' => 1,
    ]);

    $vehicle = Vehicle::create([
        'carrier_id' => $carrier->id,
        'assigned_driver_id' => $driver->id,
        'company_id' => $carrierCompany->id,
        'title' => 'Accepted cancellation truck',
        'is_available' => false,
    ]);

    $bid = Bid::create([
        'load_id' => $load->id,
        'carrier_id' => $carrier->id,
        'company_id' => $carrierCompany->id,
        'vehicle_id' => $vehicle->id,
        'status' => 'accepted',
        'accepted_at' => now(),
        'contract_accepted_at' => now(),
        'contract_signed_at' => now(),
    ]);

    $this->actingAs($shipper)
        ->patch(route('loads.cancel', $load))
        ->assertRedirect();

    $notification = FreightNotification::query()
        ->where('user_id', $carrier->id)
        ->where('type', 'load_cancelled')
        ->latest()
        ->firstOrFail();

    expect($notification->data_json['action'])->toBe('bid')
        ->and($vehicle->refresh()->is_available)->toBeTrue();

    $this->actingAs($carrier)
        ->get(route('notifications.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('notifications.data.0.type', 'load_cancelled')
            ->where('notifications.data.0.action_url', route('bids.mine'))
            ->where('notifications.data.0.action_label', 'Открыть мои отклики')
        );

    $this->actingAs($carrier)
        ->get(route('notifications.open', $notification))
        ->assertRedirect(route('bids.mine'));
});

it('creates fixed-price responses without rejecting other carriers', function () {
    Storage::fake('public');

    $shipper = freightUser('shipper');
    $otherShipper = freightUser('shipper');
    $shipperCompany = freightCompany($shipper, 'shipper');
    $carrier = freightUser('carrier');
    $carrierCompany = freightCompany($carrier, 'carrier');
    $secondCarrier = freightUser('carrier');
    $secondCarrierCompany = freightCompany($secondCarrier, 'carrier');

    $load = FreightLoad::create([
        'shipper_id' => $shipper->id,
        'company_id' => $shipperCompany->id,
        'title' => 'Fixed price load',
        'loading_city' => 'Москва',
        'unloading_city' => 'Казань',
        'loading_lat' => 55.7558,
        'loading_lng' => 37.6173,
        'price' => 62000,
        'contact_name' => 'Logistics contact',
        'contact_phone' => '+7 900 555 55 55',
        'contact_email' => 'shipper-contact@example.com',
        'delivery_confirmation_token' => 'token-fixed-price-load',
        'delivery_confirmation_code' => '123456',
        'status' => 'active',
    ]);
    $vehicle = Vehicle::create([
        'carrier_id' => $carrier->id,
        'company_id' => $carrierCompany->id,
        'title' => 'Tilt',
        'current_lat' => 55.7000,
        'current_lng' => 37.5000,
        'is_available' => true,
    ]);
    $secondVehicle = Vehicle::create([
        'carrier_id' => $secondCarrier->id,
        'company_id' => $secondCarrierCompany->id,
        'title' => 'Reefer',
        'is_available' => true,
    ]);

    $this->actingAs($carrier)
        ->post(route('bids.store', $load), [
            'vehicle_id' => $vehicle->id,
            'price' => 50000,
            'comment' => 'Ready to go',
            'contract_accepted' => true,
        ])
        ->assertRedirect();

    $this->actingAs($secondCarrier)
        ->post(route('bids.store', $load), [
            'vehicle_id' => $secondVehicle->id,
            'comment' => 'Truck is available',
            'contract_accepted' => true,
        ])
        ->assertRedirect();

    $bid = Bid::first();
    expect($bid->status)->toBe('pending')
        ->and($bid->price)->toBeNull()
        ->and(Bid::count())->toBe(2)
        ->and(FreightNotification::where('user_id', $shipper->id)->exists())->toBeTrue();

    $shipperNotification = FreightNotification::where('user_id', $shipper->id)->latest()->first();

    $this->actingAs($shipper)
        ->get(route('notifications.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('auth.unread_notifications_count', 2)
            ->where('notifications.data.0.type', 'bid_created')
            ->where('notifications.data.0.action_url', route('loads.bids', $load))
            ->where('notifications.data.0.open_url', route('notifications.open', $shipperNotification))
            ->where('notifications.data.0.action_label', 'Разобрать отклики')
        );

    $this->actingAs($shipper)
        ->get(route('notifications.open', $shipperNotification))
        ->assertRedirect(route('loads.bids', $load));

    expect($shipperNotification->refresh()->is_read)->toBeTrue();

    $this->actingAs($carrier)
        ->get(route('loads.show', $load))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('canSeeContacts', false)
            ->where('load.contact_phone', null)
            ->where('load.contact_email', null)
            ->where('load.company.phone', null)
        );

    $this->actingAs($shipper)
        ->patch(route('bids.accept', $bid))
        ->assertRedirect();

    expect($bid->refresh()->status)->toBe('accepted')
        ->and($bid->contract_signed_at)->not->toBeNull()
        ->and($load->refresh()->status)->toBe('in_progress')
        ->and($load->delivery_stage)->toBe('carrier_selected')
        ->and(Bid::where('status', 'pending')->count())->toBe(0)
        ->and(Bid::where('status', 'rejected')->count())->toBe(1)
        ->and(FreightNotification::where('user_id', $carrier->id)->exists())->toBeTrue()
        ->and(DeliveryEvent::where('load_id', $load->id)->where('type', 'carrier_selected')->exists())->toBeTrue();

    $this->actingAs($secondCarrier)
        ->get(route('notifications.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('notifications.data.0.type', 'bid_rejected')
            ->where('notifications.data.0.action_url', route('bids.mine'))
            ->where('notifications.data.0.action_label', 'Открыть мои отклики')
        );

    $this->actingAs($carrier)
        ->post(route('loads.delivery-events.store', $load), [
            'type' => 'arrived_pickup',
        ])
        ->assertSessionHasErrors('type');

    $this->actingAs($carrier)
        ->post(route('loads.delivery-events.store', $load), [
            'type' => 'en_route_to_pickup',
            'note' => 'En route to pickup.',
        ])
        ->assertRedirect();

    expect($load->refresh()->delivery_stage)->toBe('en_route_to_pickup')
        ->and(DeliveryEvent::where('load_id', $load->id)->where('type', 'en_route_to_pickup')->exists())->toBeTrue();

    $this->actingAs($carrier)
        ->post(route('loads.delivery-events.store', $load), [
            'type' => 'arrived_pickup',
            'note' => 'Arrived at warehouse.',
        ])
        ->assertRedirect();

    expect($load->refresh()->delivery_stage)->toBe('arrived_pickup')
        ->and(DeliveryEvent::where('load_id', $load->id)->where('type', 'arrived_pickup')->exists())->toBeTrue();

    $this->actingAs($shipper)
        ->get(route('notifications.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('notifications.data', fn ($notifications) => collect($notifications)->contains(
                fn ($notification) => $notification['type'] === 'delivery_event'
                    && $notification['action_url'] === route('loads.delivery', $load)
                    && $notification['action_label'] === 'Контролировать доставку'
            ))
        );

    $this->actingAs($carrier)
        ->post(route('bids.carrier-photo', $bid), [
            'carrier_cargo_photo' => UploadedFile::fake()->image('carrier-cargo.jpg', 900, 600),
        ])
        ->assertRedirect();

    expect($bid->refresh()->carrier_cargo_photo_path)->toStartWith('bid-cargo/')
        ->and($bid->carrier_cargo_photo_path)->not->toContain('data:image')
        ->and($bid->carrier_cargo_photo_meta['mime_type'])->toBe('image/jpeg')
        ->and($bid->carrier_cargo_photo_meta['optimized'])->toBeTrue();
    expect(str_ends_with($bid->carrier_cargo_photo_path, '.jpg'))->toBeTrue();
    Storage::disk('public')->assertExists($bid->carrier_cargo_photo_path);

    $this->actingAs($carrier)
        ->get(route('loads.show', $load))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('canSeeContacts', true)
            ->where('load.contact_phone', '+7 900 555 55 55')
            ->where('load.contact_email', 'shipper-contact@example.com')
            ->where('load.contract_url', route('loads.contract', $load))
            ->where('load.delivery_stage', 'arrived_pickup')
            ->where('load.delivery_confirmation.code', '123456')
            ->where('load.can_update_delivery', true)
            ->where('load.delivery_event_options.0', 'loaded')
            ->where('load.delivery_events.0.type', 'arrived_pickup')
            ->where('load.bids.0.carrier_cargo_photo_url', '/storage/'.$bid->carrier_cargo_photo_path)
            ->where('load.bids.0.can_upload_carrier_cargo_photo', true)
            ->where('routeToLoadUrl', route('map', ['load_id' => $load->id, 'route' => 1]))
        );

    $this->actingAs($carrier)
        ->post(route('loads.delivery-events.store', $load), [
            'type' => 'issue_reported',
            'note' => 'Driver reported a delay.',
            'lat' => 56.1234567,
            'lng' => 38.7654321,
        ])
        ->assertRedirect();

    expect($load->refresh()->delivery_stage)->toBe('arrived_pickup')
        ->and(DeliveryEvent::where('load_id', $load->id)->where('type', 'issue_reported')->exists())->toBeTrue()
        ->and((float) $vehicle->refresh()->current_lat)->toBe(56.1234567)
        ->and((float) $vehicle->current_lng)->toBe(38.7654321)
        ->and($vehicle->is_online)->toBeTrue()
        ->and($vehicle->last_location_at)->not->toBeNull();

    $this->actingAs($carrier)
        ->get(route('loads.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Freight/Loads/Index')
            ->has('loads.data', 0)
            ->where('stats.total', 0)
        );

    $this->actingAs($carrier)
        ->get(route('carrier.deliveries.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Freight/Carrier/Deliveries')
            ->where('deliveries.0.load.id', $load->id)
            ->where('deliveries.0.load.delivery_stage', 'arrived_pickup')
            ->where('deliveries.0.load.contact_phone', '+7 900 555 55 55')
            ->where('deliveries.0.load.route_url', route('map', ['load_id' => $load->id, 'route' => 1]))
            ->where('deliveries.0.load.delivery_confirmation.code', '123456')
            ->where('deliveries.0.load.delivery_confirmation.url', route('loads.show', ['load' => $load->id, 'confirm' => 'token-fixed-price-load']))
            ->where('deliveries.0.load.carrier_delivery_url', route('carrier.deliveries.show', $bid))
            ->where('deliveries.0.latest_event.type', 'issue_reported')
            ->where('stats.active', 1)
        );

    $this->actingAs($carrier)
        ->get(route('carrier.deliveries.show', $bid))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Freight/Carrier/DeliveryShow')
            ->where('delivery.bid_id', $bid->id)
            ->where('delivery.load.id', $load->id)
            ->where('delivery.load.contact_phone', '+7 900 555 55 55')
            ->where('delivery.load.delivery_confirmation.code', '123456')
            ->where('delivery.events.0.type', 'issue_reported')
        );

    $this->actingAs($otherShipper)
        ->get(route('loads.delivery', $load))
        ->assertForbidden();

    $this->actingAs($shipper)
        ->get(route('loads.delivery', ['load' => $load, 'confirm' => 'token-fixed-price-load']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Freight/Loads/Delivery')
            ->where('delivery.load.id', $load->id)
            ->where('delivery.load.urls.complete', route('loads.complete', $load))
            ->where('delivery.carrier.bid_id', $bid->id)
            ->where('delivery.carrier.carrier_cargo_photo_url', '/storage/'.$bid->carrier_cargo_photo_path)
            ->where('delivery.events.0.type', 'issue_reported')
            ->where('delivery.canComplete', false)
            ->where('delivery.deliveryEventOptions.0', 'shipper_note')
        );

    $this->actingAs($shipper)
        ->patch(route('loads.complete', $load), [
            'delivery_confirmation' => '123456',
        ])
        ->assertForbidden();

    $this->actingAs($shipper)
        ->post(route('loads.delivery-events.store', $load), [
            'type' => 'shipper_note',
            'note' => 'Warehouse confirmed the driver is waiting.',
            'lat' => 59.0000000,
            'lng' => 39.0000000,
        ])
        ->assertRedirect();

    expect((float) $vehicle->refresh()->current_lat)->toBe(56.1234567)
        ->and((float) $vehicle->current_lng)->toBe(38.7654321);

    $this->actingAs($secondCarrier)
        ->get(route('carrier.deliveries.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Freight/Carrier/Deliveries')
            ->has('deliveries', 0)
        );

    $this->actingAs($secondCarrier)
        ->get(route('loads.show', $load))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('canSeeContacts', false)
            ->where('load.contact_phone', null)
            ->where('load.contact_email', null)
            ->where('load.company.phone', null)
            ->where('load.contract_url', null)
            ->where('routeToLoadUrl', null)
        );

    $this->actingAs($secondCarrier)
        ->get(route('carrier.deliveries.show', $bid))
        ->assertForbidden();

    $this->actingAs($secondCarrier)
        ->get(route('loads.contract', $load))
        ->assertForbidden();

    $this->actingAs($secondCarrier)
        ->get(route('loads.contract.download', $load))
        ->assertForbidden();

    $this->actingAs($secondCarrier)
        ->getJson(route('api.map.accepted-route', $load))
        ->assertForbidden();

    $this->actingAs($shipper)
        ->get(route('carrier.deliveries.index'))
        ->assertForbidden();

    $this->actingAs($shipper)
        ->get(route('carrier.deliveries.show', $bid))
        ->assertForbidden();

    $this->actingAs($carrier)
        ->get(route('loads.contract', $load))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Freight/Loads/Contract')
            ->where('contract.load.id', $load->id)
            ->where('downloadUrl', route('loads.contract.download', $load))
        );

    $this->actingAs($shipper)
        ->get(route('loads.contract', $load))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Freight/Loads/Contract')
            ->where('contract.load.id', $load->id)
        );

    $this->actingAs($carrier)
        ->get(route('loads.contract.download', $load))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');

    $this->getJson(route('api.map.objects'))
        ->assertOk()
        ->assertJsonCount(0, 'loads');

    Http::fake([
        'router.project-osrm.org/route/v1/driving/*' => Http::response([
            'routes' => [
                [
                    'distance' => 12345.6,
                    'duration' => 1800,
                    'geometry' => [
                        'coordinates' => [
                            [37.5000, 55.7000],
                            [37.6173, 55.7558],
                        ],
                    ],
                ],
            ],
        ]),
    ]);

    $this->actingAs($carrier)
        ->getJson(route('api.map.accepted-route', $load))
        ->assertOk()
        ->assertJsonPath('distance_m', 12345.6)
        ->assertJsonPath('geometry.0.0', 55.7)
        ->assertJsonPath('geometry.0.1', 37.5);

    $this->actingAs($secondCarrier)
        ->get(route('loads.show', $load))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('canSeeContacts', false)
            ->where('load.contact_phone', null)
            ->where('load.contact_email', null)
            ->where('load.contract_url', null)
        );

    $this->actingAs($secondCarrier)
        ->get(route('loads.contract', $load))
        ->assertForbidden();

    $this->actingAs($secondCarrier)
        ->post(route('loads.delivery-events.store', $load), [
            'type' => 'in_transit',
        ])
        ->assertForbidden();

    $this->actingAs($secondCarrier)
        ->getJson(route('api.map.accepted-route', $load))
        ->assertForbidden();

    $this->actingAs($shipper)
        ->post(route('loads.delivery-events.store', $load), [
            'type' => 'issue_reported',
            'note' => 'Need extra document at unloading.',
        ])
        ->assertRedirect();

    expect($load->refresh()->delivery_stage)->toBe('arrived_pickup')
        ->and(DeliveryEvent::where('load_id', $load->id)->where('type', 'issue_reported')->exists())->toBeTrue();

    foreach ([
        'loaded' => 'Cargo loaded.',
        'in_transit' => 'Vehicle left pickup point.',
        'arrived_unloading' => 'Vehicle arrived at destination.',
        'delivered_pending_confirmation' => 'Cargo is ready for receiver confirmation.',
    ] as $type => $note) {
        $this->actingAs($carrier)
            ->post(route('loads.delivery-events.store', $load), [
                'type' => $type,
                'note' => $note,
            ])
            ->assertRedirect();
    }

    expect($load->refresh()->delivery_stage)->toBe('delivered_pending_confirmation');

    $this->actingAs($shipper)
        ->get(route('loads.delivery', ['load' => $load, 'confirm' => 'token-fixed-price-load']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('delivery.canComplete', true)
            ->where('delivery.deliveryEventOptions.0', 'shipper_note')
            ->has('delivery.deliveryEventOptions', 1)
            ->where('delivery.events.0.type', 'delivered_pending_confirmation')
        );

    $this->actingAs($shipper)
        ->get(route('loads.show', $load))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('load.delivery_event_options.0', 'shipper_note')
            ->has('load.delivery_event_options', 1)
        );

    $this->actingAs($shipper)
        ->post(route('loads.delivery-events.store', $load), [
            'type' => 'issue_reported',
            'note' => 'Too late for a problem event.',
        ])
        ->assertSessionHasErrors('type');

    $this->actingAs($shipper)
        ->patch(route('loads.complete', $load), [
            'delivery_confirmation' => '123456',
        ])
        ->assertRedirect();

    expect($load->refresh()->status)->toBe('completed')
        ->and($load->delivery_stage)->toBe('delivery_confirmed')
        ->and($load->completion_confirmed_at)->not->toBeNull()
        ->and($load->completion_confirmed_by)->toBe($shipper->id)
        ->and(DeliveryEvent::where('load_id', $load->id)->where('type', 'delivery_confirmed')->exists())->toBeTrue();
});

it('enforces vehicle eligibility for carrier responses and releases vehicles after delivery', function () {
    $shipper = freightUser('shipper', ['email' => 'eligibility-shipper@example.com']);
    $shipperCompany = freightCompany($shipper, 'shipper');
    $carrier = freightUser('carrier', ['email' => 'eligibility-carrier@example.com']);
    $carrierCompany = freightCompany($carrier, 'carrier');

    $load = FreightLoad::create([
        'shipper_id' => $shipper->id,
        'company_id' => $shipperCompany->id,
        'title' => 'Eligible load',
        'loading_city' => 'Moscow',
        'unloading_city' => 'Kazan',
        'loading_date' => '2026-07-10',
        'unloading_date' => '2026-07-12',
        'body_type' => 'tent',
        'weight_kg' => 10000,
        'volume_m3' => 40,
        'delivery_confirmation_token' => 'eligibility-token',
        'delivery_confirmation_code' => '654321',
        'status' => 'active',
    ]);

    $wrongBodyVehicle = Vehicle::create([
        'carrier_id' => $carrier->id,
        'company_id' => $carrierCompany->id,
        'title' => 'Reefer wrong body',
        'body_type' => 'refrigerator',
        'capacity_kg' => 20000,
        'volume_m3' => 80,
        'is_available' => true,
    ]);

    $smallVehicle = Vehicle::create([
        'carrier_id' => $carrier->id,
        'company_id' => $carrierCompany->id,
        'title' => 'Small tent',
        'body_type' => 'tent',
        'capacity_kg' => 5000,
        'volume_m3' => 30,
        'is_available' => true,
    ]);

    $eligibleVehicle = Vehicle::create([
        'carrier_id' => $carrier->id,
        'company_id' => $carrierCompany->id,
        'title' => 'Eligible tent',
        'body_type' => 'tent',
        'capacity_kg' => 20000,
        'volume_m3' => 82,
        'available_from_date' => '2026-07-01',
        'available_until_date' => '2026-07-20',
        'is_available' => true,
    ]);

    $this->actingAs($carrier)
        ->get(route('loads.show', $load))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('carrierVehicles.0.id', $eligibleVehicle->id)
            ->has('carrierVehicles', 1)
        );

    $this->actingAs($carrier)
        ->post(route('bids.store', $load), [
            'vehicle_id' => $wrongBodyVehicle->id,
            'contract_accepted' => true,
        ])
        ->assertSessionHasErrors('vehicle_id');

    $this->actingAs($carrier)
        ->post(route('bids.store', $load), [
            'vehicle_id' => $smallVehicle->id,
            'contract_accepted' => true,
        ])
        ->assertSessionHasErrors('vehicle_id');

    $this->actingAs($carrier)
        ->post(route('bids.store', $load), [
            'vehicle_id' => $eligibleVehicle->id,
            'comment' => 'Vehicle fits the load.',
            'contract_accepted' => true,
        ])
        ->assertRedirect();

    $bid = Bid::where('vehicle_id', $eligibleVehicle->id)->firstOrFail();

    $this->actingAs($shipper)
        ->patch(route('bids.accept', $bid))
        ->assertRedirect();

    expect($eligibleVehicle->refresh()->is_available)->toBeFalse()
        ->and($load->refresh()->status)->toBe('in_progress')
        ->and($load->bids_count)->toBe(1);

    $secondLoad = FreightLoad::create([
        'shipper_id' => $shipper->id,
        'company_id' => $shipperCompany->id,
        'title' => 'Second active load',
        'loading_city' => 'Moscow',
        'unloading_city' => 'Samara',
        'body_type' => 'tent',
        'weight_kg' => 15000,
        'volume_m3' => 60,
        'status' => 'active',
    ]);

    $this->actingAs($carrier)
        ->get(route('loads.show', $secondLoad))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('carrierVehicles', 0)
        );

    $this->actingAs($carrier)
        ->post(route('bids.carrier-photo', $bid), [
            'carrier_cargo_photo' => UploadedFile::fake()->image('eligible-carrier-cargo.jpg', 900, 600),
        ])
        ->assertRedirect();

    $carrierCargoPhotoPath = $bid->refresh()->carrier_cargo_photo_path;

    foreach ([
        'en_route_to_pickup',
        'arrived_pickup',
        'loaded',
        'in_transit',
        'arrived_unloading',
        'delivered_pending_confirmation',
    ] as $type) {
        $this->actingAs($carrier)
            ->post(route('loads.delivery-events.store', $load), [
                'type' => $type,
            ])
            ->assertRedirect();
    }

    $this->actingAs($shipper)
        ->patch(route('loads.complete', $load), [
            'delivery_confirmation' => '654321',
        ])
        ->assertRedirect();

    expect($eligibleVehicle->refresh()->is_available)->toBeTrue()
        ->and($load->refresh()->status)->toBe('completed');

    $this->actingAs($carrier)
        ->post(route('bids.carrier-photo', $bid), [
            'carrier_cargo_photo' => UploadedFile::fake()->image('late-carrier-cargo.jpg', 900, 600),
        ])
        ->assertForbidden();

    expect($bid->refresh()->carrier_cargo_photo_path)->toBe($carrierCargoPhotoPath);
});

it('allows dispatcher connections without changing load status automatically', function () {
    $dispatcher = freightUser('dispatcher');
    $shipper = freightUser('shipper');
    $shipperCompany = freightCompany($shipper, 'shipper');
    $carrier = freightUser('carrier');
    $carrierCompany = freightCompany($carrier, 'carrier');
    $carrierCompany->update(['verification_status' => 'verified']);
    $badCarrier = freightUser('carrier', ['email' => 'dispatcher-bad-carrier@example.com']);
    $badCarrierCompany = freightCompany($badCarrier, 'carrier');
    $load = FreightLoad::create([
        'shipper_id' => $shipper->id,
        'company_id' => $shipperCompany->id,
        'title' => 'Active load',
        'loading_lat' => 55.7558,
        'loading_lng' => 37.6173,
        'loading_city' => 'Москва',
        'unloading_city' => 'Казань',
        'body_type' => 'reefer',
        'weight_kg' => 7000,
        'volume_m3' => 36,
        'status' => 'active',
    ]);
    $vehicle = Vehicle::create([
        'carrier_id' => $carrier->id,
        'company_id' => $carrierCompany->id,
        'title' => 'Reefer',
        'body_type' => 'reefer',
        'capacity_kg' => 10000,
        'volume_m3' => 45,
        'current_city' => 'Moscow',
        'current_lat' => 55.7000,
        'current_lng' => 37.5000,
        'is_available' => true,
        'is_location_visible' => true,
        'is_online' => true,
    ]);
    $badVehicle = Vehicle::create([
        'carrier_id' => $badCarrier->id,
        'company_id' => $badCarrierCompany->id,
        'title' => 'Small tent',
        'body_type' => 'tent',
        'capacity_kg' => 3000,
        'volume_m3' => 12,
        'current_city' => 'Moscow',
        'current_lat' => 55.7100,
        'current_lng' => 37.5100,
        'is_available' => true,
        'is_location_visible' => true,
        'is_online' => true,
    ]);

    $this->actingAs($dispatcher)
        ->post(route('dispatcher.connections.store'), [
            'load_id' => $load->id,
            'vehicle_id' => $vehicle->id,
            'carrier_id' => $carrier->id,
            'summary' => 'Match',
        ])
        ->assertRedirect();

    expect(DispatcherConnection::count())->toBe(1)
        ->and($load->refresh()->status)->toBe('active');

    $connection = DispatcherConnection::first();

    $this->actingAs($dispatcher)
        ->post(route('dispatcher.connections.store'), [
            'load_id' => $load->id,
            'vehicle_id' => $vehicle->id,
            'carrier_id' => $carrier->id,
            'summary' => 'Duplicate match',
        ])
        ->assertSessionHasErrors('vehicle_id');

    $this->actingAs($dispatcher)
        ->post(route('dispatcher.connections.store'), [
            'load_id' => $load->id,
            'vehicle_id' => $badVehicle->id,
            'carrier_id' => $badCarrier->id,
            'summary' => 'Bad match',
        ])
        ->assertSessionHasErrors('vehicle_id');

    expect(DispatcherConnection::count())->toBe(1);

    $this->actingAs($dispatcher)
        ->get(route('dispatcher.loads.nearest-carriers', [
            'load' => $load,
            'body_type' => 'reefer',
            'online' => 1,
            'verified' => 1,
        ]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Freight/Dispatcher/Candidates')
            ->where('load.id', $load->id)
            ->where('load.connected_vehicle_ids.0', $vehicle->id)
            ->where('vehicles.0.id', $vehicle->id)
            ->where('vehicles.0.match_score', 100)
            ->where('vehicles.0.existing_connection.id', $connection->id)
            ->where('filters.body_type', 'reefer')
            ->where('filters.online', true)
            ->where('filters.verified', true)
            ->where('filterOptions.bodyTypes.0', 'reefer')
        );

    $this->actingAs($dispatcher)
        ->get(route('dispatcher.connections.show', $connection))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Freight/Dispatcher/ConnectionShow')
            ->where('connection.id', $connection->id)
            ->where('auditLogs.0.action', 'dispatcher_connection.created')
        );

    $this->actingAs($carrier)
        ->post(route('bids.store', $load), [
            'vehicle_id' => $vehicle->id,
            'comment' => 'Responding after dispatcher match.',
            'contract_accepted' => true,
        ])
        ->assertRedirect();

    $bid = Bid::where('load_id', $load->id)->where('carrier_id', $carrier->id)->first();

    expect($connection->refresh()->bid_id)->toBe($bid->id)
        ->and($connection->status)->toBe('connected')
        ->and($connection->connected_at)->not->toBeNull()
        ->and($load->refresh()->status)->toBe('active');

    $this->actingAs($dispatcher)
        ->get(route('notifications.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('notifications.data.0.type', 'dispatcher_connection')
            ->where('notifications.data.0.action_url', route('dispatcher.connections.show', $connection))
        );

    $this->actingAs($dispatcher)
        ->get(route('dispatcher.connections.show', $connection))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('connection.bid.id', $bid->id)
            ->where('connection.status', 'connected')
        );

    $this->actingAs($dispatcher)
        ->patch(route('dispatcher.connections.update', $connection), [
            'status' => 'closed',
            'internal_comment' => 'Carrier responded and connection is closed.',
        ])
        ->assertRedirect();

    $this->actingAs($dispatcher)
        ->get(route('dispatcher.connections.show', $connection))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('connection.status', 'closed')
            ->where('auditLogs.0.action', 'dispatcher_connection.updated')
            ->where('auditLogs.0.new_values_json.status', 'closed')
            ->where('auditLogs.0.new_values_json.internal_comment', 'Carrier responded and connection is closed.')
            ->where('auditLogs.1.action', 'dispatcher_connection.created')
        );

    $this->actingAs($dispatcher)
        ->patch(route('dispatcher.connections.update', $connection), [
            'status' => 'contacted',
            'internal_comment' => 'Trying to reopen closed connection.',
        ])
        ->assertSessionHasErrors('status');
});

it('enforces dispatcher and admin permissions', function () {
    $carrier = freightUser('carrier');
    $dispatcher = freightUser('dispatcher');
    $admin = freightUser('admin');

    $this->actingAs($carrier)->get(route('dispatcher.index'))->assertForbidden();
    $this->actingAs($dispatcher)->get(route('dispatcher.index'))->assertOk();
    $this->actingAs($admin)->get(route('admin.freight.index'))->assertOk();
});

it('enforces freight model policies for ownership and workflow state', function () {
    $shipper = freightUser('shipper');
    $otherShipper = freightUser('shipper');
    $carrier = freightUser('carrier');
    $otherCarrier = freightUser('carrier');
    $dispatcher = freightUser('dispatcher');
    $admin = freightUser('admin', ['email' => 'policy-admin@example.com']);
    $shipperCompany = freightCompany($shipper, 'shipper');
    $carrierCompany = freightCompany($carrier, 'carrier');

    $load = FreightLoad::create([
        'shipper_id' => $shipper->id,
        'company_id' => $shipperCompany->id,
        'title' => 'Policy load',
        'loading_city' => 'Москва',
        'unloading_city' => 'Казань',
        'status' => 'active',
    ]);

    $vehicle = Vehicle::create([
        'carrier_id' => $carrier->id,
        'company_id' => $carrierCompany->id,
        'title' => 'Policy truck',
        'is_available' => true,
    ]);

    $this->actingAs($otherShipper)
        ->put(route('loads.update', $load), [
            'title' => 'Stolen load',
            'loading_city' => 'Москва',
            'unloading_city' => 'Казань',
        ])
        ->assertForbidden();

    $this->actingAs($otherCarrier)
        ->put(route('vehicles.update', $vehicle), [
            'title' => 'Stolen truck',
        ])
        ->assertForbidden();

    $this->actingAs($otherCarrier)
        ->postJson(route('vehicles.location.update', $vehicle), [
            'lat' => 55.75,
            'lng' => 37.61,
        ])
        ->assertForbidden();

    $cancelledLoad = FreightLoad::create([
        'shipper_id' => $shipper->id,
        'company_id' => $shipperCompany->id,
        'title' => 'Cancelled policy load',
        'loading_city' => 'Москва',
        'unloading_city' => 'Казань',
        'status' => 'cancelled',
    ]);

    $this->actingAs($dispatcher)
        ->get(route('dispatcher.loads.nearest-carriers', $cancelledLoad))
        ->assertForbidden();

    $this->actingAs($dispatcher)
        ->post(route('freight.company.update'), [
            'name' => 'Dispatcher company',
        ])
        ->assertForbidden();

    $shipperCompany->update([
        'verification_status' => 'verified',
        'verification_comment' => 'Approved',
        'verified_at' => now(),
    ]);

    $this->actingAs($shipper)
        ->post(route('freight.company.update'), [
            'name' => 'Policy shipper company',
            'inn' => '7700000001',
            'bank_name' => 'Policy bank',
            'bank_bik' => '044525225',
            'bank_account' => '40702810000000000001',
            'correspondent_account' => '30101810400000000225',
        ])
        ->assertRedirect();

    expect($shipperCompany->refresh()->verification_status)->toBe('pending')
        ->and($shipperCompany->verification_comment)->toBeNull()
        ->and($shipperCompany->verified_at)->toBeNull();

    $this->actingAs($admin)
        ->get(route('notifications.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('notifications.data.0.type', 'company_review_requested')
            ->where('notifications.data.0.action_url', route('admin.freight.companies.show', $shipperCompany))
            ->where('notifications.data.0.action_label', 'Открыть компанию')
        );

    expect(FreightNotification::query()
        ->where('user_id', $admin->id)
        ->where('type', 'company_review_requested')
        ->count())->toBe(1);

    $this->actingAs($shipper)
        ->post(route('freight.company.update'), [
            'name' => 'Policy shipper company',
            'inn' => '7700000002',
            'bank_name' => 'Policy bank',
            'bank_bik' => '044525225',
            'bank_account' => '40702810000000000001',
            'correspondent_account' => '30101810400000000225',
        ])
        ->assertRedirect();

    expect(FreightNotification::query()
        ->where('user_id', $admin->id)
        ->where('type', 'company_review_requested')
        ->count())->toBe(1);

    $this->actingAs($shipper)
        ->patch(route('loads.publish', $cancelledLoad))
        ->assertForbidden();

    $this->actingAs($shipper)
        ->post(route('loads.store'), [
            'title' => 'Blocked publish load',
            'loading_city' => 'Moscow',
            'unloading_city' => 'Kazan',
            'publish' => true,
        ])
        ->assertForbidden();

    $carrierCompany->update(['verification_status' => 'pending']);

    $this->actingAs($carrier)
        ->post(route('bids.store', $load), [
            'vehicle_id' => $vehicle->id,
            'contract_accepted' => true,
        ])
        ->assertForbidden();

    $notification = FreightNotification::create([
        'user_id' => $shipper->id,
        'type' => 'policy_check',
        'title' => 'Policy notification',
        'message' => 'Only the owner can read this notification.',
    ]);

    $this->actingAs($carrier)
        ->patch(route('notifications.read', $notification))
        ->assertForbidden();

    $this->actingAs($shipper)
        ->patch(route('notifications.read', $notification))
        ->assertRedirect();

    expect($notification->refresh()->is_read)->toBeTrue();
});

it('keeps complaints tied to the reporter business context', function () {
    $shipper = freightUser('shipper', ['email' => 'complaint-shipper@example.com']);
    $otherShipper = freightUser('shipper', ['email' => 'complaint-other-shipper@example.com']);
    $carrier = freightUser('carrier', ['email' => 'complaint-carrier@example.com']);
    $driver = freightUser('carrier', ['email' => 'complaint-driver@example.com']);
    $otherCarrier = freightUser('carrier', ['email' => 'complaint-other-carrier@example.com']);
    $dispatcher = freightUser('dispatcher', ['email' => 'complaint-dispatcher@example.com']);
    $otherDispatcher = freightUser('dispatcher', ['email' => 'complaint-other-dispatcher@example.com']);
    $shipperCompany = freightCompany($shipper, 'shipper');
    $otherShipperCompany = freightCompany($otherShipper, 'shipper');
    $carrierCompany = freightCompany($carrier, 'carrier');
    $carrierCompany->update([
        'carrier_profile_type' => 'company',
        'allows_carrier_members' => true,
    ]);
    $carrierCompany->carrierMembers()->syncWithoutDetaching([
        $driver->id => ['role' => 'driver', 'status' => 'active', 'joined_at' => now()],
    ]);

    $load = FreightLoad::create([
        'shipper_id' => $shipper->id,
        'company_id' => $shipperCompany->id,
        'title' => 'Complaint load',
        'loading_city' => 'Moscow',
        'unloading_city' => 'Kazan',
        'status' => 'active',
    ]);
    $otherLoad = FreightLoad::create([
        'shipper_id' => $otherShipper->id,
        'company_id' => $otherShipperCompany->id,
        'title' => 'Other complaint load',
        'loading_city' => 'Moscow',
        'unloading_city' => 'Samara',
        'status' => 'active',
    ]);
    $assignedLoad = FreightLoad::create([
        'shipper_id' => $shipper->id,
        'company_id' => $shipperCompany->id,
        'title' => 'Assigned complaint load',
        'loading_city' => 'Moscow',
        'unloading_city' => 'Tula',
        'status' => 'in_progress',
    ]);
    $vehicle = Vehicle::create([
        'carrier_id' => $carrier->id,
        'company_id' => $carrierCompany->id,
        'title' => 'Complaint truck',
        'is_available' => true,
    ]);
    $driverVehicle = Vehicle::create([
        'carrier_id' => $carrier->id,
        'assigned_driver_id' => $driver->id,
        'company_id' => $carrierCompany->id,
        'title' => 'Assigned complaint truck',
        'is_available' => true,
    ]);
    $bid = Bid::create([
        'load_id' => $load->id,
        'carrier_id' => $carrier->id,
        'company_id' => $carrierCompany->id,
        'vehicle_id' => $vehicle->id,
        'status' => 'pending',
    ]);
    $connection = DispatcherConnection::create([
        'dispatcher_id' => $dispatcher->id,
        'load_id' => $load->id,
        'shipper_id' => $shipper->id,
        'shipper_company_id' => $shipperCompany->id,
        'carrier_id' => $carrier->id,
        'carrier_company_id' => $carrierCompany->id,
        'vehicle_id' => $vehicle->id,
        'bid_id' => $bid->id,
        'status' => 'connected',
    ]);
    $assignedBid = Bid::create([
        'load_id' => $assignedLoad->id,
        'carrier_id' => $carrier->id,
        'company_id' => $carrierCompany->id,
        'vehicle_id' => $driverVehicle->id,
        'status' => 'accepted',
    ]);
    $assignedConnection = DispatcherConnection::create([
        'dispatcher_id' => $dispatcher->id,
        'load_id' => $assignedLoad->id,
        'shipper_id' => $shipper->id,
        'shipper_company_id' => $shipperCompany->id,
        'carrier_id' => $carrier->id,
        'carrier_company_id' => $carrierCompany->id,
        'vehicle_id' => $driverVehicle->id,
        'bid_id' => $assignedBid->id,
        'status' => 'connected',
    ]);

    $this->actingAs($carrier)
        ->post(route('complaints.store'), [
            'load_id' => $load->id,
            'bid_id' => $bid->id,
            'target_user_id' => $shipper->id,
            'type' => 'payment_issue',
            'message' => 'Payment is late.',
        ])
        ->assertRedirect();

    $this->actingAs($otherCarrier)
        ->post(route('complaints.store'), [
            'load_id' => $load->id,
            'type' => 'other',
            'message' => 'Trying to attach чужой груз.',
        ])
        ->assertSessionHasErrors('load_id');

    $this->actingAs($shipper)
        ->post(route('complaints.store'), [
            'load_id' => $otherLoad->id,
            'type' => 'other',
            'message' => 'Trying to attach чужой груз.',
        ])
        ->assertSessionHasErrors('load_id');

    $this->actingAs($carrier)
        ->post(route('complaints.store'), [
            'target_user_id' => $shipper->id,
            'type' => 'other',
            'message' => 'No business context.',
        ])
        ->assertSessionHasErrors('target_user_id');

    $this->actingAs($carrier)
        ->post(route('complaints.store'), [
            'load_id' => $load->id,
            'target_user_id' => $carrier->id,
            'type' => 'other',
            'message' => 'Self complaint.',
        ])
        ->assertSessionHasErrors('target_user_id');

    $this->actingAs($carrier)
        ->post(route('complaints.store'), [
            'load_id' => $load->id,
            'target_user_id' => $otherCarrier->id,
            'type' => 'other',
            'message' => 'Unrelated target.',
        ])
        ->assertSessionHasErrors('target_user_id');

    $this->actingAs($otherDispatcher)
        ->post(route('complaints.store'), [
            'dispatcher_connection_id' => $connection->id,
            'type' => 'other',
            'message' => 'Trying чужое соединение.',
        ])
        ->assertSessionHasErrors('dispatcher_connection_id');

    $this->actingAs($driver)
        ->post(route('complaints.store'), [
            'load_id' => $load->id,
            'bid_id' => $bid->id,
            'type' => 'other',
            'message' => 'Driver should not complain about another company bid.',
        ])
        ->assertSessionHasErrors('load_id');

    $this->actingAs($driver)
        ->post(route('complaints.store'), [
            'dispatcher_connection_id' => $connection->id,
            'type' => 'other',
            'message' => 'Driver should not complain about another company connection.',
        ])
        ->assertSessionHasErrors('load_id');

    $this->actingAs($driver)
        ->post(route('complaints.store'), [
            'load_id' => $assignedLoad->id,
            'bid_id' => $assignedBid->id,
            'dispatcher_connection_id' => $assignedConnection->id,
            'target_user_id' => $shipper->id,
            'type' => 'no_show',
            'message' => 'Assigned driver can complain about assigned delivery.',
        ])
        ->assertRedirect();

    $this->actingAs($dispatcher)
        ->post(route('complaints.store'), [
            'dispatcher_connection_id' => $connection->id,
            'type' => 'other',
            'message' => 'Valid dispatcher complaint.',
        ])
        ->assertRedirect();

    expect(Complaint::count())->toBe(3);
});

it('allows admins to moderate freight entities and complaints', function () {
    $admin = freightUser('admin');
    $shipper = freightUser('shipper');
    $shipperCompany = freightCompany($shipper, 'shipper');
    $carrier = freightUser('carrier');
    $carrierCompany = freightCompany($carrier, 'carrier');

    $load = FreightLoad::create([
        'shipper_id' => $shipper->id,
        'company_id' => $shipperCompany->id,
        'title' => 'Moderated load',
        'loading_city' => 'Москва',
        'unloading_city' => 'Казань',
        'status' => 'active',
    ]);

    $vehicle = Vehicle::create([
        'carrier_id' => $carrier->id,
        'company_id' => $carrierCompany->id,
        'title' => 'Moderated truck',
        'is_available' => true,
        'is_location_visible' => true,
        'is_online' => true,
    ]);

    $complaint = Complaint::create([
        'reporter_id' => $shipper->id,
        'target_user_id' => $carrier->id,
        'load_id' => $load->id,
        'type' => 'wrong_contacts',
        'message' => 'Телефон не отвечает',
        'status' => 'new',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.freight.index', ['q' => 'Moderated', 'load_status' => 'active']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Freight/Admin/Index')
            ->where('filters.q', 'Moderated')
            ->has('loads', 1)
        );

    foreach ([
        route('admin.freight.users.show', $shipper) => 'user',
        route('admin.freight.companies.show', $shipperCompany) => 'company',
        route('admin.freight.loads.show', $load) => 'load',
        route('admin.freight.vehicles.show', $vehicle) => 'vehicle',
    ] as $url => $type) {
        $this->actingAs($admin)
            ->get($url)
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Freight/Admin/EntityShow')
                ->where('type', $type)
            );
    }

    $shipperCompany->update(['verification_status' => 'pending', 'verified_at' => null]);

    $this->actingAs($admin)
        ->patch(route('admin.freight.companies.update', $shipperCompany), [
            'verification_status' => 'verified',
            'verification_comment' => 'Documents checked.',
        ])
        ->assertRedirect();

    $this->actingAs($admin)
        ->patch(route('admin.freight.loads.update', $load), [
            'status' => 'archived',
            'is_featured' => true,
        ])
        ->assertRedirect();

    $this->actingAs($admin)
        ->patch(route('admin.freight.vehicles.update', $vehicle), [
            'is_available' => false,
            'is_location_visible' => false,
            'is_online' => false,
        ])
        ->assertRedirect();

    $this->actingAs($admin)
        ->patch(route('admin.freight.complaints.update', $complaint), [
            'status' => 'in_review',
            'admin_comment' => 'Проверяем контакты.',
        ])
        ->assertRedirect();

    expect($shipperCompany->refresh()->verification_status)->toBe('verified')
        ->and($shipperCompany->verification_comment)->toBe('Documents checked.')
        ->and($shipperCompany->verified_at)->not()->toBeNull()
        ->and($load->refresh()->status)->toBe('archived')
        ->and($load->is_featured)->toBeTrue()
        ->and($vehicle->refresh()->is_available)->toBeFalse()
        ->and($vehicle->is_location_visible)->toBeFalse()
        ->and($vehicle->is_online)->toBeFalse()
        ->and($complaint->refresh()->status)->toBe('in_review')
        ->and($complaint->admin_comment)->toBe('Проверяем контакты.');

    $this->actingAs($shipper)
        ->get(route('notifications.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('notifications.data.0.type', 'company_moderation')
            ->where('notifications.data.0.action_url', route('freight.company.edit'))
            ->where('notifications.data.0.action_label', 'Открыть профиль')
        );
});
