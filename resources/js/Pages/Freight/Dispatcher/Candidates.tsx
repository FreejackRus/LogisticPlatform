import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { formatDateRange } from '@/lib/utils';
import { Head, Link, router } from '@inertiajs/react';
import { AlertTriangle, CheckCircle2, Filter, MapPinned, Phone, Truck } from 'lucide-react';

type Load = {
    id: number;
    title: string;
    status: string;
    loading_city: string;
    loading_region?: string | null;
    loading_address?: string | null;
    unloading_city: string;
    unloading_region?: string | null;
    unloading_address?: string | null;
    loading_date?: string | null;
    unloading_date?: string | null;
    cargo_type?: string | null;
    body_type?: string | null;
    weight_kg?: number | null;
    volume_m3?: number | null;
    price?: number | null;
    is_urgent?: boolean;
    connections_count: number;
    connected_vehicle_ids: number[];
    company?: {
        name?: string | null;
        verification_status?: string | null;
    };
    urls: {
        show: string;
        map: string;
    };
};

type Vehicle = {
    id: number;
    title: string;
    body_type?: string | null;
    capacity_kg?: number | null;
    volume_m3?: number | null;
    current_city?: string | null;
    current_region?: string | null;
    distance_km: number;
    match_score: number;
    match_warnings: string[];
    is_online: boolean;
    last_location_at?: string | null;
    carrier_id?: number | null;
    registration_number?: string | null;
    company?: {
        name?: string | null;
        phone?: string | null;
        email?: string | null;
        verification_status?: string | null;
        rating?: number | null;
        reviews_count?: number | null;
    };
    driver?: {
        name?: string | null;
        phone?: string | null;
    } | null;
    existing_connection?: {
        id: number;
        status: string;
        url: string;
    } | null;
    urls: {
        show: string;
    };
};

type Props = {
    load: Load;
    vehicles: Vehicle[];
    filters: {
        body_type?: string | null;
        online: boolean;
        verified: boolean;
    };
    filterOptions: {
        bodyTypes: string[];
    };
    disclaimer: string;
};

const verificationLabels: Record<string, string> = {
    not_verified: 'Не проверена',
    pending: 'На проверке',
    verified: 'Проверена',
    rejected: 'Отклонена',
};

const connectionStatusLabels: Record<string, string> = {
    draft: 'Черновик',
    proposed: 'Предложено',
    contacted: 'Стороны уведомлены',
    connected: 'Стороны связаны',
    declined: 'Отказ',
    no_answer: 'Нет ответа',
    cancelled: 'Отменено',
    closed: 'Закрыто',
};

export default function Candidates({ load, vehicles, filters, filterOptions, disclaimer }: Props) {
    const applyFilters = (next: Partial<Props['filters']>) => {
        router.get(
            route('dispatcher.loads.nearest-carriers', load.id),
            {
                body_type: next.body_type ?? filters.body_type ?? '',
                online: (next.online ?? filters.online) ? 1 : 0,
                verified: (next.verified ?? filters.verified) ? 1 : 0,
            },
            { preserveScroll: true, preserveState: true },
        );
    };

    const connect = (vehicle: Vehicle) => {
        router.post(route('dispatcher.connections.store'), {
            load_id: load.id,
            vehicle_id: vehicle.id,
            carrier_id: vehicle.carrier_id,
            contact_method: 'platform_notification',
            summary: `Подбор транспорта ${vehicle.title} для груза ${load.title}`,
            shipper_message: `Диспетчер подобрал кандидата ${vehicle.company?.name || vehicle.title} для груза ${load.title}.`,
            carrier_message: `Диспетчер предлагает груз ${load.title} для транспорта ${vehicle.title}.`,
        });
    };

    const routeText = [load.loading_city, load.unloading_city].filter(Boolean).join(' - ');
    const price = load.price ? `${load.price.toLocaleString('ru-RU')} руб.` : 'цена не указана';

    return (
        <AuthenticatedLayout breadcrumbs={[
            { title: 'Диспетчер', href: route('dispatcher.index') },
            { title: 'Кандидаты' },
        ]}>
            <Head title="Кандидаты перевозчиков" />
            <div className="mx-auto grid max-w-7xl gap-5 px-3 py-4 sm:px-4 sm:py-6">
                <div className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-start">
                    <div>
                        <div className="flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
                            <Truck className="size-4" />
                            Подбор перевозчиков
                            {load.is_urgent && <Badge variant="destructive">Срочно</Badge>}
                        </div>
                        <h1 className="mt-2 text-2xl font-semibold leading-tight">{load.title}</h1>
                        <p className="mt-1 text-muted-foreground">{routeText}</p>
                    </div>
                    <div className="grid grid-cols-2 gap-2 sm:flex sm:flex-wrap">
                        <Button asChild variant="secondary">
                            <Link href={load.urls.show}>Карточка груза</Link>
                        </Button>
                        <Button asChild variant="secondary">
                            <Link href={load.urls.map}>
                                <MapPinned className="size-4" />
                                На карте
                            </Link>
                        </Button>
                    </div>
                </div>

                <section className="grid gap-3 md:grid-cols-4">
                    <Info label="Маршрут" value={routeText} />
                    <Info label="Кузов" value={load.body_type || 'любой'} />
                    <Info label="Вес" value={load.weight_kg ? `${load.weight_kg.toLocaleString('ru-RU')} кг` : 'не указан'} />
                    <Info label="Цена" value={price} />
                </section>

                <section className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_360px]">
                    <div className="rounded-md border p-4">
                        <h2 className="font-semibold">Требования груза</h2>
                        <div className="mt-3 grid gap-3 text-sm md:grid-cols-2">
                            <Line label="Погрузка" value={[load.loading_city, load.loading_region, load.loading_address].filter(Boolean).join(', ')} />
                            <Line label="Выгрузка" value={[load.unloading_city, load.unloading_region, load.unloading_address].filter(Boolean).join(', ')} />
                            <Line label="Даты" value={formatDateRange(load.loading_date, load.unloading_date)} />
                            <Line label="Груз" value={[load.cargo_type, load.body_type].filter(Boolean).join(' · ')} />
                            <Line label="Объём" value={load.volume_m3 ? `${load.volume_m3} м3` : null} />
                            <Line label="Уже создано соединений" value={load.connections_count} />
                        </div>
                    </div>
                    <div className="rounded-md border border-amber-300 bg-amber-50 p-4 text-sm text-amber-950">
                        {disclaimer}
                    </div>
                </section>

                <section className="grid gap-3 rounded-md border p-3 md:grid-cols-[1fr_auto_auto_auto] md:items-center">
                    <div className="flex items-center gap-2 font-medium">
                        <Filter className="size-4" />
                        Фильтры подбора
                    </div>
                    <select
                        value={filters.body_type ?? ''}
                        onChange={(event) => applyFilters({ body_type: event.target.value })}
                        className="min-h-10 rounded-md border bg-background px-3 text-sm"
                    >
                        <option value="">Любой кузов</option>
                        {filterOptions.bodyTypes.map((bodyType) => (
                            <option key={bodyType} value={bodyType}>{bodyType}</option>
                        ))}
                    </select>
                    <label className="flex min-h-10 items-center gap-2 rounded-md border px-3 text-sm">
                        <input
                            type="checkbox"
                            checked={filters.online}
                            onChange={(event) => applyFilters({ online: event.target.checked })}
                        />
                        Онлайн
                    </label>
                    <label className="flex min-h-10 items-center gap-2 rounded-md border px-3 text-sm">
                        <input
                            type="checkbox"
                            checked={filters.verified}
                            onChange={(event) => applyFilters({ verified: event.target.checked })}
                        />
                        Проверенные
                    </label>
                </section>

                <section className="grid gap-3">
                    {vehicles.length === 0 ? (
                        <div className="rounded-md border border-dashed p-8 text-center">
                            <h2 className="font-semibold">Кандидатов не найдено</h2>
                            <p className="mt-1 text-sm text-muted-foreground">
                                Попробуйте снять фильтр кузова, онлайн-статуса или проверки компании.
                            </p>
                        </div>
                    ) : (
                        vehicles.map((vehicle) => (
                            <VehicleCard
                                key={vehicle.id}
                                vehicle={vehicle}
                                alreadyConnected={load.connected_vehicle_ids.includes(vehicle.id)}
                                onConnect={() => connect(vehicle)}
                            />
                        ))
                    )}
                </section>
            </div>
        </AuthenticatedLayout>
    );
}

function VehicleCard({ vehicle, alreadyConnected, onConnect }: { vehicle: Vehicle; alreadyConnected: boolean; onConnect: () => void }) {
    const companyName = vehicle.company?.name || 'Компания не указана';
    const location = [vehicle.current_city, vehicle.current_region].filter(Boolean).join(', ') || 'локация не указана';
    const scoreTone = vehicle.match_score >= 80
        ? 'border-emerald-200 bg-emerald-50 text-emerald-800'
        : vehicle.match_score >= 55
            ? 'border-amber-200 bg-amber-50 text-amber-800'
            : 'border-rose-200 bg-rose-50 text-rose-800';

    return (
        <article className="grid gap-4 rounded-md border p-4 lg:grid-cols-[minmax(0,1fr)_260px]">
            <div className="grid gap-3">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <div className="flex flex-wrap items-center gap-2">
                            <Badge className={scoreTone} variant="outline">
                                Совпадение {vehicle.match_score}%
                            </Badge>
                            {vehicle.is_online ? (
                                <Badge className="border-emerald-200 bg-emerald-50 text-emerald-800" variant="outline">Онлайн</Badge>
                            ) : (
                                <Badge variant="secondary">Офлайн</Badge>
                            )}
                            {alreadyConnected && <Badge variant="secondary">Уже связано</Badge>}
                        </div>
                        <h2 className="mt-2 font-semibold">{companyName}</h2>
                        <Link className="text-sm text-muted-foreground underline" href={vehicle.urls.show}>
                            {[vehicle.title, vehicle.registration_number].filter(Boolean).join(' · ')}
                        </Link>
                    </div>
                    <div className="text-sm text-muted-foreground lg:text-right">
                        <p className="font-medium text-foreground">{vehicle.distance_km} км до погрузки</p>
                        <p>{location}</p>
                    </div>
                </div>

                <div className="grid gap-2 text-sm md:grid-cols-4">
                    <Line label="Кузов" value={vehicle.body_type} />
                    <Line label="Грузоподъёмность" value={vehicle.capacity_kg ? `${vehicle.capacity_kg.toLocaleString('ru-RU')} кг` : null} />
                    <Line label="Объём" value={vehicle.volume_m3 ? `${vehicle.volume_m3} м3` : null} />
                    <Line label="Проверка" value={verificationLabel(vehicle.company?.verification_status)} />
                </div>

                {vehicle.match_warnings.length > 0 ? (
                    <div className="grid gap-2">
                        {vehicle.match_warnings.map((warning) => (
                            <div key={warning} className="flex gap-2 rounded-md border border-amber-200 bg-amber-50 p-2 text-sm text-amber-900">
                                <AlertTriangle className="mt-0.5 size-4 shrink-0" />
                                {warning}
                            </div>
                        ))}
                    </div>
                ) : (
                    <div className="flex gap-2 rounded-md border border-emerald-200 bg-emerald-50 p-2 text-sm text-emerald-900">
                        <CheckCircle2 className="mt-0.5 size-4 shrink-0" />
                        Ключевые требования груза совпадают с параметрами транспорта.
                    </div>
                )}
            </div>

            <aside className="grid content-start gap-3 rounded-md border bg-muted/20 p-3">
                <Line label="Телефон компании" value={vehicle.company?.phone} />
                <Line label="Email компании" value={vehicle.company?.email} />
                <Line label="Водитель" value={vehicle.driver?.name} />
                <Line label="Телефон водителя" value={vehicle.driver?.phone} />
                <Line label="Последняя геолокация" value={vehicle.last_location_at} />
                <div className="grid gap-2 pt-1">
                    {vehicle.company?.phone && (
                        <Button asChild size="sm" variant="secondary">
                            <a href={`tel:${vehicle.company.phone}`}>
                                <Phone className="size-4" />
                                Позвонить
                            </a>
                        </Button>
                    )}
                    {vehicle.existing_connection ? (
                        <Button asChild size="sm" variant="secondary">
                            <Link href={vehicle.existing_connection.url}>
                                {connectionStatusLabels[vehicle.existing_connection.status] ?? 'Открыть соединение'}
                            </Link>
                        </Button>
                    ) : (
                        <Button size="sm" onClick={onConnect} disabled={!vehicle.carrier_id}>
                            Создать соединение
                        </Button>
                    )}
                </div>
            </aside>
        </article>
    );
}

function Info({ label, value }: { label: string; value?: string | number | null }) {
    return (
        <div className="rounded-md border p-3">
            <p className="text-xs text-muted-foreground">{label}</p>
            <p className="font-medium">{value || 'не указано'}</p>
        </div>
    );
}

function Line({ label, value }: { label: string; value?: string | number | null }) {
    return (
        <div>
            <dt className="text-xs text-muted-foreground">{label}</dt>
            <dd>{value || '-'}</dd>
        </div>
    );
}

function verificationLabel(status?: string | null) {
    return status ? verificationLabels[status] ?? status : '-';
}
