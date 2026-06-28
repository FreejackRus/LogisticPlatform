<?php

return [
    'legal_disclaimer' => implode("\n", [
        'Платформа является информационным сервисом для поиска грузов и перевозчиков.',
        'Платформа не является перевозчиком, экспедитором, агентом или стороной договора перевозки.',
        'Все договоренности, расчеты, документы и ответственность по перевозке возникают напрямую между грузовладельцем и перевозчиком.',
        'Диспетчер платформы может помочь пользователям найти друг друга, но не заключает договор перевозки от имени платформы и не принимает оплату за перевозку.',
    ]),

    'notification_disclaimer' => 'Платформа только передает контакты. Договоренности и ответственность возникают напрямую между сторонами.',

    'contracts' => [
        'terms_version' => env('FREIGHT_CONTRACT_TERMS_VERSION', '2026-06'),
        'text' => 'Перевозчик подтверждает готовность выполнить перевозку по условиям объявления, а заказчик при выборе отклика подтверждает заключение прямого договора перевозки с выбранным перевозчиком. Платформа не является стороной договора.',
    ],

    'options' => [
        'body_types' => [
            'tent' => 'Тент',
            'refrigerator' => 'Рефрижератор',
            'isothermal' => 'Изотерм',
            'board' => 'Бортовой',
            'container' => 'Контейнер',
            'van' => 'Фургон',
            'open_platform' => 'Открытая площадка',
        ],
        'vehicle_types' => [
            'truck' => 'Грузовик',
            'van' => 'Фургон',
            'tractor' => 'Тягач',
            'refrigerator' => 'Рефрижератор',
        ],
        'cargo_types' => [
            'pallets' => 'Паллеты',
            'equipment' => 'Оборудование',
            'food' => 'Продукты',
            'building_materials' => 'Стройматериалы',
            'furniture' => 'Мебель',
            'other' => 'Другое',
        ],
        'loading_types' => [
            'rear' => 'Задняя',
            'side' => 'Боковая',
            'top' => 'Верхняя',
            'manual' => 'Ручная',
            'crane' => 'Кран',
        ],
        'payment_types' => [
            'negotiable' => 'По договоренности',
            'bank_transfer' => 'Безналичный расчет',
            'cash' => 'Наличные',
            'card' => 'Карта',
        ],
        'carrier_profile_types' => [
            'individual' => 'Индивидуальный перевозчик',
            'company' => 'Транспортная компания',
        ],
    ],

    'map' => [
        'provider' => env('MAP_PROVIDER', 'openstreetmap'),
        'default_lat' => (float) env('DEFAULT_MAP_LAT', 55.7558),
        'default_lng' => (float) env('DEFAULT_MAP_LNG', 37.6173),
        'default_zoom' => (int) env('DEFAULT_MAP_ZOOM', 5),
        'refresh_seconds' => (int) env('MAP_OBJECTS_REFRESH_SECONDS', 20),
        'tile_url' => env('OPEN_MAP_TILE_URL', 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png'),
        'attribution' => env('OPEN_MAP_ATTRIBUTION', 'OpenStreetMap'),
    ],

    'location' => [
        'update_interval_seconds' => (int) env('LOCATION_UPDATE_INTERVAL_SECONDS', 30),
        'online_timeout_minutes' => (int) env('VEHICLE_ONLINE_TIMEOUT_MINUTES', 5),
        'retention_days' => (int) env('LOCATION_PINGS_RETENTION_DAYS', 30),
    ],

    'geocoding' => [
        'provider' => env('GEOCODING_PROVIDER', 'photon'),
        'cache_ttl_seconds' => (int) env('GEOCODING_CACHE_TTL_SECONDS', 86400),
        'suggest_limit' => (int) env('GEOCODING_SUGGEST_LIMIT', 6),
        'request_timeout_seconds' => (int) env('GEOCODING_REQUEST_TIMEOUT_SECONDS', 5),
    ],
];
