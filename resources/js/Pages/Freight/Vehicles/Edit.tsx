import InputError from '@/Components/InputError';
import { AddressSuggestInput, type AddressSuggestion } from '@/Components/Freight/AddressSuggestInput';
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
    vehicle_type?: string;
    body_type?: string;
    registration_number?: string;
    trailer_number?: string;
    capacity_kg?: number;
    volume_m3?: number;
    length_m?: number;
    width_m?: number;
    height_m?: number;
    current_city?: string;
    current_region?: string;
    current_lat?: number;
    current_lng?: number;
    assigned_driver_id?: number | null;
    is_available: boolean;
    is_location_visible: boolean;
    available_from_date?: string;
    available_until_date?: string;
    preferred_regions?: string[];
    preferred_routes?: string[];
    description?: string;
    photo_url?: string | null;
};

type Options = {
    body_types: Record<string, string>;
    vehicle_types: Record<string, string>;
};

type Driver = { id: number; name: string; email: string };

export default function Edit({ vehicle, options, drivers }: { vehicle: Vehicle; options: Options; drivers: Driver[] }) {
    const t = useFreightTranslation();
    const { data, setData, post, processing, errors } = useForm({
        _method: 'put',
        title: vehicle.title ?? '',
        vehicle_type: vehicle.vehicle_type ?? 'truck',
        body_type: vehicle.body_type ?? '',
        registration_number: vehicle.registration_number ?? '',
        trailer_number: vehicle.trailer_number ?? '',
        capacity_kg: vehicle.capacity_kg ? String(vehicle.capacity_kg) : '',
        volume_m3: vehicle.volume_m3 ? String(vehicle.volume_m3) : '',
        length_m: vehicle.length_m ? String(vehicle.length_m) : '',
        width_m: vehicle.width_m ? String(vehicle.width_m) : '',
        height_m: vehicle.height_m ? String(vehicle.height_m) : '',
        current_city: vehicle.current_city ?? '',
        current_region: vehicle.current_region ?? '',
        current_lat: vehicle.current_lat ? String(vehicle.current_lat) : '',
        current_lng: vehicle.current_lng ? String(vehicle.current_lng) : '',
        assigned_driver_id: vehicle.assigned_driver_id ? String(vehicle.assigned_driver_id) : '',
        is_available: vehicle.is_available,
        is_location_visible: vehicle.is_location_visible,
        available_from_date: vehicle.available_from_date ? String(vehicle.available_from_date).slice(0, 10) : '',
        available_until_date: vehicle.available_until_date ? String(vehicle.available_until_date).slice(0, 10) : '',
        preferred_regions: (vehicle.preferred_regions ?? []).join(', '),
        preferred_routes: (vehicle.preferred_routes ?? []).join('\n'),
        description: vehicle.description ?? '',
        photo: null as File | null,
    });

    const submit: FormEventHandler = (event) => {
        event.preventDefault();
        post(route('vehicles.update', vehicle.id), { forceFormData: true });
    };

    const applyCitySuggestion = (suggestion: AddressSuggestion) => {
        if (suggestion.lat && suggestion.lng) {
            setData('current_lat', String(suggestion.lat));
            setData('current_lng', String(suggestion.lng));
        }
    };

    return (
        <AuthenticatedLayout breadcrumbs={[{ title: t('vehicles.title'), href: route('vehicles.mine') }, { title: vehicle.title, href: route('vehicles.show', vehicle.id) }, { title: t('loads.breadcrumb_edit') }]}>
            <Head title={`${t('vehicles.edit_title')}: ${vehicle.title}`} />
            <form onSubmit={submit} className="mx-auto grid max-w-5xl gap-5 px-4 py-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <h1 className="text-2xl font-semibold">{t('vehicles.edit_title')}</h1>
                    <Button asChild variant="secondary">
                        <Link href={route('vehicles.show', vehicle.id)}>{t('vehicles.open_card')}</Link>
                    </Button>
                </div>

                <div className="grid gap-4 rounded-md border p-4 md:grid-cols-2">
                    {[
                        'title',
                        'registration_number',
                        'trailer_number',
                        'capacity_kg',
                        'volume_m3',
                        'length_m',
                        'width_m',
                        'height_m',
                        'current_city',
                        'current_region',
                        'available_from_date',
                        'available_until_date',
                    ].map((key) => (
                        <div key={key} className="grid gap-2">
                            <Label htmlFor={key}>{t(`vehicles.fields.${key}`)}</Label>
                            {key === 'current_city' ? (
                                <AddressSuggestInput
                                    id={key}
                                    value={data.current_city}
                                    onChange={(value) => setData('current_city', value)}
                                    onSelect={applyCitySuggestion}
                                    valueFromSuggestion={(suggestion) => suggestion.city || suggestion.title}
                                />
                            ) : (
                                <Input
                                    id={key}
                                    type={key.includes('date') ? 'date' : 'text'}
                                    value={String(data[key as keyof typeof data])}
                                    onChange={(event) => setData(key as keyof typeof data, event.target.value)}
                                    required={key === 'title'}
                                />
                            )}
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
                        {vehicle.photo_url && <img src={vehicle.photo_url} alt="" className="h-28 w-full rounded-md object-cover" />}
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
                    <div className="grid gap-2 md:col-span-2">
                        <Label htmlFor="preferred_regions">{t('vehicles.fields.preferred_regions')}</Label>
                        <Input
                            id="preferred_regions"
                            value={data.preferred_regions}
                            onChange={(event) => setData('preferred_regions', event.target.value)}
                            placeholder="Москва, Московская область, Татарстан"
                        />
                        <InputError message={errors.preferred_regions} />
                    </div>
                    <div className="grid gap-2 md:col-span-2">
                        <Label htmlFor="preferred_routes">{t('vehicles.fields.preferred_routes')}</Label>
                        <textarea
                            id="preferred_routes"
                            value={data.preferred_routes}
                            onChange={(event) => setData('preferred_routes', event.target.value)}
                            className="min-h-24 rounded-md border bg-background p-3 text-sm"
                            placeholder="Москва - Казань"
                        />
                        <InputError message={errors.preferred_routes} />
                    </div>
                    <div className="grid gap-2 md:col-span-2">
                        <Label htmlFor="description">{t('vehicles.fields.description')}</Label>
                        <textarea
                            id="description"
                            value={data.description}
                            onChange={(event) => setData('description', event.target.value)}
                            className="min-h-28 rounded-md border bg-background p-3 text-sm"
                        />
                    </div>
                </div>

                <Button className="w-fit" disabled={processing}>{t('common.save_changes')}</Button>
            </form>
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
