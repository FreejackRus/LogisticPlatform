<?php

namespace Database\Seeders;

use App\Models\Bid;
use App\Models\Company;
use App\Models\Complaint;
use App\Models\DeliveryEvent;
use App\Models\DispatcherConnection;
use App\Models\FreightLoad;
use App\Models\LocationPing;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class FreightExchangeSeeder extends Seeder
{
    private array $cities = [
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

    public function run(): void
    {
        $admin = $this->user('Админ', 'admin@example.com', 'admin');
        $dispatcher = $this->user('Диспетчер', 'dispatcher@example.com', 'dispatcher');
        $shipper = $this->user('Грузовладелец', 'shipper@example.com', 'shipper');
        $carrier = $this->user('Перевозчик', 'carrier@example.com', 'carrier');

        $shippers = collect([$shipper]);
        for ($i = 1; $i <= 9; $i++) {
            $shippers->push($this->user("Грузовладелец {$i}", "shipper{$i}@example.com", 'shipper'));
        }

        $carriers = collect([$carrier]);
        for ($i = 1; $i <= 29; $i++) {
            $carriers->push($this->user("Перевозчик {$i}", "carrier{$i}@example.com", 'carrier'));
        }

        $dispatchers = collect([$dispatcher]);
        for ($i = 1; $i <= 2; $i++) {
            $dispatchers->push($this->user("Диспетчер {$i}", "dispatcher{$i}@example.com", 'dispatcher'));
        }

        $shippers->each(fn (User $user, int $i) => $this->company($user, 'shipper', "Грузовая компания {$i}"));
        $carriers->each(fn (User $user, int $i) => $this->company($user, 'carrier', "ТК Перевозчик {$i}"));

        $loads = collect();
        for ($i = 1; $i <= 130; $i++) {
            $from = $this->city($i);
            $to = $this->city($i + 7);
            $shipperUser = $shippers[$i % $shippers->count()];
            $status = $i <= 100 ? 'active' : ($i <= 120 ? 'completed' : 'cancelled');
            $title = "Груз {$i}: {$from[0]} - {$to[0]}";
            $loads->push(FreightLoad::updateOrCreate(
                ['title' => $title],
                [
                    'shipper_id' => $shipperUser->id,
                    'company_id' => $shipperUser->company->id,
                    'cargo_type' => ['паллеты', 'оборудование', 'продукты', 'стройматериалы'][$i % 4],
                    'cargo_description' => 'Тестовый груз для демонстрации биржи.',
                    'loading_city' => $from[0],
                    'loading_lat' => $from[1][0],
                    'loading_lng' => $from[1][1],
                    'unloading_city' => $to[0],
                    'unloading_lat' => $to[1][0],
                    'unloading_lng' => $to[1][1],
                    'loading_date' => now()->addDays($i % 14)->toDateString(),
                    'weight_kg' => 1000 + ($i * 137) % 19000,
                    'volume_m3' => 10 + ($i % 70),
                    'body_type' => ['тент', 'рефрижератор', 'изотерм', 'бортовой'][$i % 4],
                    'price' => 45000 + $i * 1100,
                    'price_currency' => 'RUB',
                    'payment_type' => 'bank_transfer',
                    'contact_name' => $shipperUser->name,
                    'contact_phone' => $shipperUser->phone,
                    'contact_email' => $shipperUser->email,
                    'delivery_confirmation_token' => substr(hash('sha256', "demo-load-{$i}"), 0, 40),
                    'delivery_confirmation_code' => (string) (100000 + $i),
                    'status' => $status,
                    'is_urgent' => $i % 9 === 0,
                    'published_at' => $status === 'active' ? now()->subHours($i) : null,
                    'completed_at' => $status === 'completed' ? now()->subDays(2) : null,
                    'cancelled_at' => $status === 'cancelled' ? now()->subDays(1) : null,
                ],
            ));
        }

        $vehicles = collect();
        for ($i = 1; $i <= 60; $i++) {
            $city = $this->city($i + 3);
            $carrierUser = $carriers[$i % $carriers->count()];
            $hasCoordinates = $i <= 40;
            $online = $i <= 25;
            $registrationNumber = 'А'.str_pad((string) $i, 3, '0', STR_PAD_LEFT).'ВС77';
            $vehicles->push(Vehicle::updateOrCreate(
                ['registration_number' => $registrationNumber],
                [
                    'carrier_id' => $carrierUser->id,
                    'company_id' => $carrierUser->company->id,
                    'title' => "Автомобиль {$i}",
                    'vehicle_type' => 'truck',
                    'body_type' => ['тент', 'рефрижератор', 'изотерм', 'бортовой'][$i % 4],
                    'capacity_kg' => 5000 + ($i * 500) % 15000,
                    'volume_m3' => 30 + ($i % 60),
                    'current_city' => $hasCoordinates ? $city[0] : null,
                    'current_lat' => $hasCoordinates ? $city[1][0] + ($i % 5) / 100 : null,
                    'current_lng' => $hasCoordinates ? $city[1][1] + ($i % 5) / 100 : null,
                    'is_available' => true,
                    'is_online' => $online,
                    'is_location_visible' => $hasCoordinates,
                    'last_location_at' => $hasCoordinates ? ($online ? now()->subMinutes($i % 4) : now()->subMinutes(30 + $i)) : null,
                ],
            ));
        }

        for ($i = 1; $i <= 200; $i++) {
            $load = $loads[$i % 100];
            $carrierUser = $carriers[$i % $carriers->count()];
            $vehicle = $vehicles[$i % $vehicles->count()];
            Bid::firstOrCreate(
                ['load_id' => $load->id, 'carrier_id' => $carrierUser->id],
                [
                    'company_id' => $carrierUser->company->id,
                    'vehicle_id' => $vehicle->id,
                    'price_currency' => 'RUB',
                    'comment' => 'Готовы выполнить перевозку.',
                    'status' => 'pending',
                ],
            );
        }

        $demoCarrierVehicle = $vehicles->firstWhere('carrier_id', $carrier->id) ?? $vehicles->first();
        $loads->take(3)->values()->each(function (FreightLoad $load, int $index) use ($carrier, $demoCarrierVehicle) {
            $bid = Bid::updateOrCreate(
                ['load_id' => $load->id, 'carrier_id' => $carrier->id],
                [
                    'company_id' => $carrier->company->id,
                    'vehicle_id' => $demoCarrierVehicle?->id,
                    'price_currency' => 'RUB',
                    'comment' => 'Демо-перевозка принята в работу.',
                    'status' => 'accepted',
                    'contract_accepted_at' => now()->subHours(8 - $index),
                    'contract_signed_at' => now()->subHours(8 - $index),
                    'accepted_at' => now()->subHours(8 - $index),
                    'rejected_at' => null,
                    'cancelled_at' => null,
                ],
            );

            $stage = ['carrier_selected', 'en_route_to_pickup', 'loaded'][$index] ?? 'carrier_selected';
            $load->update([
                'status' => 'in_progress',
                'delivery_stage' => $stage,
                'published_at' => null,
                'completed_at' => null,
                'cancelled_at' => null,
                'bids_count' => $load->bids()->whereIn('status', ['pending', 'accepted'])->count(),
            ]);

            DeliveryEvent::updateOrCreate(
                [
                    'load_id' => $load->id,
                    'bid_id' => $bid->id,
                    'type' => $stage,
                ],
                [
                    'actor_id' => $carrier->id,
                    'note' => 'Демо-событие для мобильного кабинета перевозчика.',
                    'lat' => $demoCarrierVehicle?->current_lat,
                    'lng' => $demoCarrierVehicle?->current_lng,
                    'created_at' => now()->subHours(6 - $index),
                    'updated_at' => now()->subHours(6 - $index),
                ],
            );
        });

        $loads->each(fn (FreightLoad $load) => $load->update(['bids_count' => $load->bids()->count()]));

        for ($i = 1; $i <= 30; $i++) {
            $load = $loads[$i];
            $vehicle = $vehicles[$i];
            DispatcherConnection::updateOrCreate(
                [
                    'dispatcher_id' => $dispatchers[$i % $dispatchers->count()]->id,
                    'load_id' => $load->id,
                    'carrier_id' => $vehicle->carrier_id,
                    'vehicle_id' => $vehicle->id,
                ],
                [
                    'shipper_id' => $load->shipper_id,
                    'shipper_company_id' => $load->company_id,
                    'carrier_company_id' => $vehicle->company_id,
                    'status' => ['proposed', 'contacted', 'connected', 'closed'][$i % 4],
                    'contact_method' => 'platform_notification',
                    'summary' => 'Тестовое ручное соединение сторон.',
                    'internal_comment' => 'Создано сидером.',
                ],
            );
        }

        $vehicles->take(40)->each(function (Vehicle $vehicle) {
            LocationPing::updateOrCreate(
                [
                    'vehicle_id' => $vehicle->id,
                    'source' => 'browser',
                ],
                [
                    'carrier_id' => $vehicle->carrier_id,
                    'lat' => $vehicle->current_lat,
                    'lng' => $vehicle->current_lng,
                    'accuracy_meters' => 25,
                    'created_at' => $vehicle->last_location_at ?? now(),
                ],
            );
        });

        for ($i = 1; $i <= 20; $i++) {
            Complaint::updateOrCreate(
                [
                    'reporter_id' => $shippers[$i % $shippers->count()]->id,
                    'target_user_id' => $carriers[$i % $carriers->count()]->id,
                    'type' => ['wrong_contacts', 'no_show', 'spam', 'other'][$i % 4],
                    'message' => 'Тестовая жалоба для админ-панели.',
                ],
                [
                    'status' => ['new', 'in_review', 'resolved'][$i % 3],
                ],
            );
        }

        $admin->forceFill(['role' => 'admin'])->save();
    }

    private function user(string $name, string $email, string $role): User
    {
        return User::updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'phone' => '+7 900 '.random_int(100, 999).' '.random_int(10, 99).' '.random_int(10, 99),
                'role' => $role,
                'language_preference' => 'ru',
                'password' => Hash::make('password'),
                'is_active' => true,
                'is_blocked' => false,
                'email_verified_at' => now(),
            ],
        );
    }

    private function company(User $user, string $type, string $name): Company
    {
        return Company::updateOrCreate(
            ['user_id' => $user->id],
            [
                'type' => $type,
                'name' => $name,
                'short_name' => $name,
                'inn' => (string) random_int(1000000000, 9999999999),
                'phone' => $user->phone,
                'email' => $user->email,
                'carrier_profile_type' => $type === 'carrier' ? ($user->email === 'carrier@example.com' ? 'company' : 'individual') : 'individual',
                'allows_carrier_members' => $type === 'carrier' && $user->email === 'carrier@example.com',
                'verification_status' => 'verified',
            ],
        );
    }

    private function city(int $index): array
    {
        $name = array_keys($this->cities)[$index % count($this->cities)];

        return [$name, $this->cities[$name]];
    }
}
