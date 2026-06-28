<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <title>Договор перевозки груза №{{ $load->id }}-{{ $bid->id }}</title>
    <style>
        body {
            color: #111827;
            font-family: "DejaVu Sans", sans-serif;
            font-size: 12px;
            line-height: 1.45;
        }

        h1 {
            font-size: 20px;
            margin: 0 0 12px;
            text-align: center;
        }

        h2 {
            border-bottom: 1px solid #d1d5db;
            font-size: 14px;
            margin: 18px 0 8px;
            padding-bottom: 4px;
        }

        table {
            border-collapse: collapse;
            width: 100%;
        }

        th, td {
            border: 1px solid #d1d5db;
            padding: 6px 8px;
            vertical-align: top;
        }

        th {
            background: #f3f4f6;
            text-align: left;
            width: 34%;
        }

        .muted {
            color: #6b7280;
        }

        .notice {
            background: #fffbeb;
            border: 1px solid #f59e0b;
            margin-top: 18px;
            padding: 10px 12px;
        }

        .signature-grid {
            display: table;
            margin-top: 28px;
            width: 100%;
        }

        .signature-cell {
            display: table-cell;
            padding-right: 20px;
            width: 50%;
        }

        .signature-line {
            border-top: 1px solid #111827;
            margin-top: 36px;
            padding-top: 6px;
        }
    </style>
</head>
<body>
    <h1>Договор перевозки груза №{{ $load->id }}-{{ $bid->id }}</h1>
    <p class="muted">
        Сформирован платформой LogisticPlatform {{ $generatedAt->format('d.m.Y H:i') }}.
        Версия условий: {{ $termsVersion }}.
    </p>

    <h2>Стороны</h2>
    <table>
        <tr>
            <th>Заказчик</th>
            <td>
                {{ $load->company?->name ?: $load->shipper?->name }}<br>
                @if($load->company?->inn) ИНН: {{ $load->company->inn }}<br>@endif
                @if($load->company?->legal_address) Адрес: {{ $load->company->legal_address }}<br>@endif
                Контакт: {{ $load->contact_name ?: $load->shipper?->name }}<br>
                Телефон: {{ $load->contact_phone ?: $load->company?->phone ?: 'не указан' }}<br>
                Email: {{ $load->contact_email ?: $load->company?->email ?: 'не указан' }}
            </td>
        </tr>
        <tr>
            <th>Перевозчик</th>
            <td>
                {{ $bid->company?->name ?: $bid->carrier?->company?->name ?: $bid->carrier?->name }}<br>
                @if($bid->company?->inn) ИНН: {{ $bid->company->inn }}<br>@endif
                @if($bid->company?->legal_address) Адрес: {{ $bid->company->legal_address }}<br>@endif
                Контакт: {{ $bid->carrier?->name }}<br>
                Телефон: {{ $bid->carrier?->phone ?: $bid->company?->phone ?: $bid->carrier?->company?->phone ?: 'не указан' }}<br>
                Email: {{ $bid->carrier?->email ?: $bid->company?->email ?: $bid->carrier?->company?->email ?: 'не указан' }}
            </td>
        </tr>
    </table>

    <h2>Груз и маршрут</h2>
    <table>
        <tr><th>Груз</th><td>{{ $load->title }}</td></tr>
        <tr><th>Описание</th><td>{{ $load->cargo_description ?: $load->cargo_type ?: 'не указано' }}</td></tr>
        <tr><th>Погрузка</th><td>{{ collect([$load->loading_city, $load->loading_region, $load->loading_address])->filter()->join(', ') }}</td></tr>
        <tr><th>Выгрузка</th><td>{{ collect([$load->unloading_city, $load->unloading_region, $load->unloading_address])->filter()->join(', ') }}</td></tr>
        <tr><th>Дата погрузки</th><td>{{ $load->loading_date?->format('d.m.Y') ?: 'не указана' }}</td></tr>
        <tr><th>Дата выгрузки</th><td>{{ $load->unloading_date?->format('d.m.Y') ?: 'не указана' }}</td></tr>
        <tr>
            <th>Вес / объем / мест</th>
            <td>
                {{ $load->weight_kg ? $load->weight_kg.' кг' : 'не указан' }} /
                {{ $load->volume_m3 ? $load->volume_m3.' м3' : 'не указан' }} /
                {{ $load->places_count ?: 'не указано' }}
            </td>
        </tr>
    </table>

    <h2>Транспорт и цена</h2>
    <table>
        <tr><th>Транспорт</th><td>{{ $bid->vehicle?->title ?: 'не указан' }}</td></tr>
        <tr><th>Госномер</th><td>{{ $bid->vehicle?->registration_number ?: 'не указан' }}</td></tr>
        <tr><th>Тип кузова</th><td>{{ $bid->vehicle?->body_type ?: $load->body_type ?: 'не указан' }}</td></tr>
        <tr><th>Стоимость</th><td>{{ $load->price ? number_format($load->price, 0, ',', ' ').' RUB' : 'по договоренности' }}</td></tr>
        <tr><th>Оплата</th><td>{{ $load->payment_type ?: 'не указана' }} {{ $load->payment_terms ? ' / '.$load->payment_terms : '' }}</td></tr>
    </table>

    <h2>Подписание</h2>
    <p>
        Перевозчик подтвердил условия объявления при отправке отклика:
        {{ $bid->contract_accepted_at?->format('d.m.Y H:i') ?: 'дата не указана' }}.
        Заказчик выбрал перевозчика и подтвердил заключение прямого договора:
        {{ $bid->contract_signed_at?->format('d.m.Y H:i') ?: 'дата не указана' }}.
    </p>

    <div class="notice">
        <strong>Роль платформы.</strong><br>
        Платформа является информационным сервисом и не становится стороной договора перевозки.
        Все расчеты, документы, ответственность за груз и исполнение перевозки возникают напрямую между заказчиком и перевозчиком.
    </div>

    <h2>Отметки исполнения</h2>
    <table>
        <tr><th>Статус груза</th><td>{{ $load->status }}</td></tr>
        <tr><th>Код подтверждения доставки</th><td>{{ $load->delivery_confirmation_code ?: 'не указан' }}</td></tr>
        <tr><th>Доставка подтверждена</th><td>{{ $load->completion_confirmed_at?->format('d.m.Y H:i') ?: 'нет' }}</td></tr>
    </table>

    <div class="signature-grid">
        <div class="signature-cell">
            <div class="signature-line">Заказчик: {{ $load->company?->name ?: $load->shipper?->name }}</div>
        </div>
        <div class="signature-cell">
            <div class="signature-line">Перевозчик: {{ $bid->company?->name ?: $bid->carrier?->company?->name ?: $bid->carrier?->name }}</div>
        </div>
    </div>
</body>
</html>
