import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { useFreightTranslation } from '@/hooks/useFreightTranslation';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

type Vehicle = {
    id: number;
    title: string;
    vehicle_type?: string;
    body_type?: string;
    registration_number?: string;
    capacity_kg?: number;
    volume_m3?: number;
    current_city?: string;
    current_region?: string;
    is_online: boolean;
    is_location_visible: boolean;
    company?: { name?: string; phone?: string };
    carrier?: { name?: string; phone?: string };
};

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type PaginatedVehicles = {
    data: Vehicle[];
    from: number | null;
    to: number | null;
    total: number;
    links: PaginationLink[];
};

type Filters = {
    q?: string;
    city?: string;
    body_type?: string;
    min_capacity?: string | number;
    min_volume?: string | number;
    online?: string | boolean;
    sort?: string;
};

type Props = {
    vehicles: PaginatedVehicles;
    filters: Filters;
    filterOptions: { bodyTypes: string[] };
    stats: { total: number; online: number };
    canSeeContacts: boolean;
};

function cleanParams(filters: Filters) {
    return Object.fromEntries(
        Object.entries(filters).filter(([, value]) => value !== '' && value !== undefined && value !== false),
    );
}

export default function Catalog({ vehicles, filters, filterOptions, stats, canSeeContacts }: Props) {
    const t = useFreightTranslation();
    const [form, setForm] = useState<Filters>({
        q: filters.q ?? '',
        city: filters.city ?? '',
        body_type: filters.body_type ?? '',
        min_capacity: filters.min_capacity ?? '',
        min_volume: filters.min_volume ?? '',
        online: Boolean(filters.online),
        sort: filters.sort ?? 'newest',
    });

    const setField = (field: keyof Filters, value: string | boolean) => {
        setForm((current) => ({ ...current, [field]: value }));
    };

    const submit: FormEventHandler = (event) => {
        event.preventDefault();
        router.get(route('vehicles.index'), cleanParams(form), {
            preserveScroll: true,
            preserveState: true,
        });
    };

    const reset = () => {
        router.get(route('vehicles.index'), {}, { preserveScroll: true });
    };

    return (
        <AuthenticatedLayout breadcrumbs={[{ title: t('vehicles.catalog_title') }]}>
            <Head title={t('vehicles.catalog_title')} />
            <div className="px-4 py-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold">{t('vehicles.catalog_title')}</h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {t('vehicles.catalog_subtitle', {
                                total: t('vehicles.vehicle_count', { count: stats.total }),
                                online: t('vehicles.online_count', { count: stats.online }),
                            })}
                        </p>
                    </div>
                    <Button asChild variant="secondary">
                        <Link href={route('map')}>{t('common.map')}</Link>
                    </Button>
                </div>

                <form onSubmit={submit} className="mt-5 grid gap-3 rounded-md border p-4 md:grid-cols-12">
                    <Input
                        className="md:col-span-4"
                        value={String(form.q ?? '')}
                        onChange={(event) => setField('q', event.target.value)}
                        placeholder={t('vehicles.filters.search_placeholder')}
                    />
                    <Input
                        className="md:col-span-2"
                        value={String(form.city ?? '')}
                        onChange={(event) => setField('city', event.target.value)}
                        placeholder={t('vehicles.filters.city')}
                    />
                    <select
                        className="h-10 rounded-md border border-input bg-background px-3 text-sm md:col-span-2"
                        value={String(form.body_type ?? '')}
                        onChange={(event) => setField('body_type', event.target.value)}
                    >
                        <option value="">{t('vehicles.filters.any_body')}</option>
                        {filterOptions.bodyTypes.map((bodyType) => (
                            <option key={bodyType} value={bodyType}>
                                {bodyType}
                            </option>
                        ))}
                    </select>
                    <Input
                        className="md:col-span-2"
                        inputMode="numeric"
                        value={String(form.min_capacity ?? '')}
                        onChange={(event) => setField('min_capacity', event.target.value)}
                        placeholder={t('vehicles.filters.min_capacity')}
                    />
                    <Input
                        className="md:col-span-2"
                        inputMode="decimal"
                        value={String(form.min_volume ?? '')}
                        onChange={(event) => setField('min_volume', event.target.value)}
                        placeholder={t('vehicles.filters.min_volume')}
                    />
                    <select
                        className="h-10 rounded-md border border-input bg-background px-3 text-sm md:col-span-3"
                        value={String(form.sort ?? 'newest')}
                        onChange={(event) => setField('sort', event.target.value)}
                    >
                        <option value="newest">{t('vehicles.sort.newest')}</option>
                        <option value="capacity_desc">{t('vehicles.sort.capacity_desc')}</option>
                        <option value="volume_desc">{t('vehicles.sort.volume_desc')}</option>
                    </select>
                    <label className="flex h-10 items-center gap-2 rounded-md border px-3 text-sm md:col-span-2">
                        <input
                            type="checkbox"
                            checked={Boolean(form.online)}
                            onChange={(event) => setField('online', event.target.checked)}
                        />
                        {t('vehicles.filters.online')}
                    </label>
                    <div className="flex gap-2 md:col-span-7">
                        <Button type="submit" className="min-w-28">{t('vehicles.filters.search')}</Button>
                        <Button type="button" variant="secondary" onClick={reset}>{t('vehicles.filters.reset')}</Button>
                    </div>
                </form>

                <div className="mt-4 text-sm text-muted-foreground">
                    {vehicles.total > 0
                        ? t('vehicles.results', { from: vehicles.from ?? 0, to: vehicles.to ?? 0, total: vehicles.total })
                        : t('vehicles.empty_title')}
                </div>

                <div className="mt-4 grid gap-3">
                    {vehicles.data.map((vehicle) => (
                        <Link key={vehicle.id} href={route('vehicles.show', vehicle.id)} className="rounded-md border p-4 hover:bg-muted">
                            <div className="flex flex-wrap items-start justify-between gap-3">
                                <div className="min-w-0">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <h2 className="font-semibold">{vehicle.title}</h2>
                                        <Badge variant={vehicle.is_online ? 'default' : 'secondary'}>
                                            {vehicle.is_online ? t('vehicles.online') : t('vehicles.offline')}
                                        </Badge>
                                        {vehicle.body_type && <Badge variant="secondary">{vehicle.body_type}</Badge>}
                                    </div>
                                    <p className="mt-1 text-sm text-muted-foreground">
                                        {[vehicle.current_city, vehicle.current_region].filter(Boolean).join(', ') || t('vehicles.no_coordinates')}
                                    </p>
                                    <p className="mt-1 text-xs text-muted-foreground">
                                        {[
                                            vehicle.capacity_kg ? t('vehicles.capacity_short', { capacity: vehicle.capacity_kg.toLocaleString('ru-RU') }) : null,
                                            vehicle.volume_m3 ? t('vehicles.volume_short', { volume: vehicle.volume_m3 }) : null,
                                            vehicle.is_location_visible ? t('vehicles.on_map') : t('vehicles.hidden_from_map'),
                                        ].filter(Boolean).join(' · ')}
                                    </p>
                                </div>
                                <div className="text-left text-sm md:text-right">
                                    <p className="font-medium">{vehicle.company?.name || vehicle.carrier?.name || t('common.not_specified')}</p>
                                    <p className="text-muted-foreground">{vehicle.vehicle_type || t('common.transport')}</p>
                                    {canSeeContacts && (vehicle.company?.phone || vehicle.carrier?.phone) && (
                                        <p>{vehicle.company?.phone || vehicle.carrier?.phone}</p>
                                    )}
                                </div>
                            </div>
                        </Link>
                    ))}

                    {vehicles.data.length === 0 && (
                        <div className="rounded-md border border-dashed p-8 text-center">
                            <h2 className="font-semibold">{t('vehicles.empty_title')}</h2>
                            <p className="mt-2 text-sm text-muted-foreground">{t('vehicles.empty_text')}</p>
                            <Button type="button" variant="secondary" className="mt-4" onClick={reset}>
                                {t('vehicles.filters.reset')}
                            </Button>
                        </div>
                    )}
                </div>

                {vehicles.links.length > 3 && (
                    <div className="mt-6 flex flex-wrap gap-2">
                        {vehicles.links.map((link) => (
                            link.url ? (
                                <Link
                                    key={`${link.label}-${link.url}`}
                                    href={link.url}
                                    preserveScroll
                                    className={`rounded-md border px-3 py-2 text-sm ${link.active ? 'bg-primary text-primary-foreground' : 'hover:bg-muted'}`}
                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                />
                            ) : (
                                <span
                                    key={link.label}
                                    className="rounded-md border px-3 py-2 text-sm text-muted-foreground"
                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                />
                            )
                        ))}
                    </div>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
