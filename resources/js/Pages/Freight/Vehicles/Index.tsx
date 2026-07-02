import InputError from '@/Components/InputError';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useFreightTranslation } from '@/hooks/useFreightTranslation';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

type Vehicle = {
    id: number;
    title: string;
    body_type?: string;
    registration_number?: string;
    capacity_kg?: number;
    is_available: boolean;
    is_location_visible: boolean;
    is_online: boolean;
    current_city?: string;
    photo_url?: string | null;
    assigned_driver?: { id: number; name: string; email: string } | null;
    can_update_location: boolean;
};

type VehicleForm = {
    title: string;
    vehicle_type: string;
    body_type: string;
    registration_number: string;
    capacity_kg: string;
    volume_m3: string;
    current_city: string;
    current_region: string;
    assigned_driver_id: string;
    is_available: boolean;
    is_location_visible: boolean;
    description: string;
    photo: File | null;
};

type Options = {
    body_types: Record<string, string>;
    vehicle_types: Record<string, string>;
};

type Driver = { id: number; name: string; email: string };

type Props = {
    vehicles: Vehicle[];
    options: Options;
    drivers: Driver[];
    canCreateVehicle: boolean;
    canManageFleet: boolean;
    canUpdateLocation: boolean;
    isDriverWorkspace: boolean;
    activeCarrierCompany?: { id: number; name: string; carrier_profile_type?: string | null } | null;
};

export default function Index({
    vehicles,
    options,
    drivers,
    canCreateVehicle,
    canManageFleet,
    canUpdateLocation,
    isDriverWorkspace,
    activeCarrierCompany,
}: Props) {
    const t = useFreightTranslation();
    const { data, setData, post, processing, errors } = useForm<VehicleForm>({
        title: '',
        vehicle_type: 'truck',
        body_type: '',
        registration_number: '',
        capacity_kg: '',
        volume_m3: '',
        current_city: '',
        current_region: '',
        assigned_driver_id: '',
        is_available: true,
        is_location_visible: true,
        description: '',
        photo: null,
    });

    const submit: FormEventHandler = (event) => {
        event.preventDefault();
        post(route('vehicles.store'), { forceFormData: true });
    };

    return (
        <AuthenticatedLayout breadcrumbs={[{ title: isDriverWorkspace ? 'Назначенный транспорт' : t('vehicles.title') }]}>
            <Head title={isDriverWorkspace ? 'Назначенный транспорт' : t('vehicles.title')} />
            <div className="grid gap-6 px-4 py-6">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold">
                            {isDriverWorkspace ? 'Назначенный транспорт' : t('vehicles.title')}
                        </h1>
                        <p className="mt-1 max-w-2xl text-sm text-muted-foreground">
                            {isDriverWorkspace
                                ? 'Здесь только машины, которые назначены вам как водителю. Добавление и редактирование парка выполняет владелец или менеджер транспортной компании.'
                                : activeCarrierCompany?.carrier_profile_type === 'company'
                                    ? `Парк компании ${activeCarrierCompany.name}: машины, водители, доступность и публикация на карте.`
                                    : 'Ваш транспорт, доступность, геолокация и параметры для откликов на грузы.'}
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {canUpdateLocation && (
                            <Button asChild variant="secondary">
                                <Link href={route('carrier.location')}>{t('vehicles.geo')}</Link>
                            </Button>
                        )}
                        <Button asChild variant="secondary">
                            <Link href={route('carrier.deliveries.index')}>Мои рейсы</Link>
                        </Button>
                    </div>
                </div>

                {isDriverWorkspace && (
                    <div className="rounded-md border border-amber-200 bg-amber-50 p-4 text-sm text-amber-950">
                        Водительский режим: вы можете обновлять геолокацию назначенной машины, открывать карточку транспорта и вести назначенные рейсы. Данные парка и назначение водителей меняет компания.
                    </div>
                )}

                {canCreateVehicle && <form onSubmit={submit} className="grid gap-4 rounded-md border p-4 md:grid-cols-3">
                    {['title', 'registration_number', 'capacity_kg', 'volume_m3', 'current_city'].map((key) => (
                        <div key={key} className="grid gap-2">
                            <Label htmlFor={key}>{t(`vehicles.fields.${key}`)}</Label>
                            <Input
                                id={key}
                                value={String(data[key as keyof typeof data])}
                                onChange={(event) => setData(key as keyof typeof data, event.target.value)}
                                required={key === 'title'}
                            />
                            <InputError message={errors[key as keyof typeof errors]} />
                        </div>
                    ))}
                    <SelectField id="vehicle_type" label={t('vehicles.fields.vehicle_type')} value={data.vehicle_type} options={options.vehicle_types} onChange={(value) => setData('vehicle_type', value)} error={errors.vehicle_type} />
                    <SelectField id="body_type" label={t('vehicles.fields.body_type')} value={data.body_type} options={options.body_types} onChange={(value) => setData('body_type', value)} error={errors.body_type} />
                    {drivers.length > 0 && (
                        <SelectField
                            id="assigned_driver_id"
                            label={t('vehicles.fields.assigned_driver')}
                            value={data.assigned_driver_id}
                            options={Object.fromEntries(drivers.map((driver) => [String(driver.id), `${driver.name} · ${driver.email}`]))}
                            onChange={(value) => setData('assigned_driver_id', value)}
                            error={errors.assigned_driver_id}
                        />
                    )}
                    <div className="grid gap-2">
                        <Label htmlFor="photo">Фото транспорта</Label>
                        <Input id="photo" type="file" accept="image/*" onChange={(event) => setData('photo', event.target.files?.[0] ?? null)} />
                        <InputError message={errors.photo} />
                    </div>
                    <label className="flex items-center gap-2 text-sm">
                        <input type="checkbox" checked={data.is_available} onChange={(event) => setData('is_available', event.target.checked)} />
                        {t('vehicles.available')}
                    </label>
                    <label className="flex items-center gap-2 text-sm">
                        <input type="checkbox" checked={data.is_location_visible} onChange={(event) => setData('is_location_visible', event.target.checked)} />
                        {t('vehicles.show_on_map')}
                    </label>
                    <Button className="w-fit" disabled={processing}>{t('vehicles.add')}</Button>
                </form>}
                <div className="grid gap-3">
                    {vehicles.length === 0 && (
                        <div className="rounded-md border border-dashed p-8 text-center">
                            <h2 className="font-semibold">
                                {isDriverWorkspace ? 'Назначенных машин пока нет' : 'Транспорт пока не добавлен'}
                            </h2>
                            <p className="mt-2 text-sm text-muted-foreground">
                                {isDriverWorkspace
                                    ? 'Когда компания назначит вам машину, она появится здесь и станет доступна для геолокации и рейсов.'
                                    : 'Добавьте машину, чтобы откликаться на грузы и показывать транспорт на карте.'}
                            </p>
                        </div>
                    )}
                    {vehicles.map((vehicle) => (
                        <div key={vehicle.id} className="rounded-md border p-4">
                            <div className="flex flex-wrap justify-between gap-3">
                                <div>
                                    {vehicle.photo_url && <img src={vehicle.photo_url} alt="" className="mb-3 h-28 w-44 rounded-md object-cover" />}
                                    <h2 className="font-semibold">{vehicle.title}</h2>
                                    {vehicle.assigned_driver && (
                                        <p className="text-sm text-muted-foreground">
                                            {t('vehicles.fields.assigned_driver')}: {vehicle.assigned_driver.name}
                                        </p>
                                    )}
                                    <p className="text-sm text-muted-foreground">
                                        {[vehicle.body_type, vehicle.registration_number].filter(Boolean).join(' · ')}
                                    </p>
                                    <p className="text-sm">{vehicle.current_city || t('vehicles.no_coordinates')}</p>
                                </div>
                                <div className="text-sm">
                                    <p>{vehicle.is_available ? t('vehicles.available') : t('vehicles.unavailable')}</p>
                                    <p>{vehicle.is_location_visible ? t('vehicles.on_map') : t('vehicles.hidden_from_map')}</p>
                                    <p>{vehicle.is_online ? t('vehicles.online') : t('vehicles.offline')}</p>
                                    <div className="mt-3 flex flex-wrap gap-2">
                                        <Button asChild size="sm" variant="secondary">
                                            <Link href={route('vehicles.show', vehicle.id)}>{t('common.open')}</Link>
                                        </Button>
                                        {canManageFleet && (
                                            <Button asChild size="sm">
                                                <Link href={route('vehicles.edit', vehicle.id)}>{t('common.edit')}</Link>
                                            </Button>
                                        )}
                                    </div>
                                </div>
                            </div>
                        </div>
                    ))}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

function SelectField({
    id,
    label,
    value,
    options,
    error,
    onChange,
}: {
    id: string;
    label: string;
    value: string;
    options: Record<string, string>;
    error?: string;
    onChange: (value: string) => void;
}) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={id}>{label}</Label>
            <select
                id={id}
                value={value}
                onChange={(event) => onChange(event.target.value)}
                className="rounded-md border bg-background px-3 py-2 text-sm"
            >
                <option value="">Не указано</option>
                {Object.entries(options).map(([optionValue, optionLabel]) => (
                    <option key={optionValue} value={optionValue}>{optionLabel}</option>
                ))}
            </select>
            <InputError message={error} />
        </div>
    );
}
