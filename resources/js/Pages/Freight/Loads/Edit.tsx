import InputError from '@/Components/InputError';
import { AddressSuggestInput, type AddressSuggestion } from '@/Components/Freight/AddressSuggestInput';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useFreightTranslation } from '@/hooks/useFreightTranslation';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

type Load = {
    id: number;
    title: string;
    cargo_type?: string;
    cargo_description?: string;
    loading_city: string;
    loading_region?: string;
    loading_address?: string;
    loading_lat?: number;
    loading_lng?: number;
    unloading_city: string;
    unloading_region?: string;
    unloading_address?: string;
    unloading_lat?: number;
    unloading_lng?: number;
    loading_date?: string;
    loading_time_from?: string;
    loading_time_to?: string;
    unloading_date?: string;
    unloading_time_from?: string;
    unloading_time_to?: string;
    weight_kg?: number;
    volume_m3?: number;
    places_count?: number;
    body_type?: string;
    loading_type?: string;
    temperature_mode?: string;
    price?: number;
    price_with_vat?: boolean;
    payment_type?: string;
    payment_terms?: string;
    contact_name?: string;
    contact_phone?: string;
    contact_email?: string;
    is_urgent: boolean;
    cargo_photo_url?: string | null;
};

type Options = {
    body_types: Record<string, string>;
    cargo_types: Record<string, string>;
    loading_types: Record<string, string>;
    payment_types: Record<string, string>;
};

export default function Edit({ load, disclaimer, options }: { load: Load; disclaimer: string; options: Options }) {
    const t = useFreightTranslation();
    const { data, setData, post, processing, errors } = useForm({
        _method: 'put',
        title: load.title ?? '',
        cargo_type: load.cargo_type ?? '',
        cargo_description: load.cargo_description ?? '',
        loading_city: load.loading_city ?? '',
        loading_region: load.loading_region ?? '',
        loading_address: load.loading_address ?? '',
        loading_lat: load.loading_lat ? String(load.loading_lat) : '',
        loading_lng: load.loading_lng ? String(load.loading_lng) : '',
        unloading_city: load.unloading_city ?? '',
        unloading_region: load.unloading_region ?? '',
        unloading_address: load.unloading_address ?? '',
        unloading_lat: load.unloading_lat ? String(load.unloading_lat) : '',
        unloading_lng: load.unloading_lng ? String(load.unloading_lng) : '',
        loading_date: load.loading_date ? String(load.loading_date).slice(0, 10) : '',
        loading_time_from: load.loading_time_from ? String(load.loading_time_from).slice(0, 5) : '',
        loading_time_to: load.loading_time_to ? String(load.loading_time_to).slice(0, 5) : '',
        unloading_date: load.unloading_date ? String(load.unloading_date).slice(0, 10) : '',
        unloading_time_from: load.unloading_time_from ? String(load.unloading_time_from).slice(0, 5) : '',
        unloading_time_to: load.unloading_time_to ? String(load.unloading_time_to).slice(0, 5) : '',
        weight_kg: load.weight_kg ? String(load.weight_kg) : '',
        volume_m3: load.volume_m3 ? String(load.volume_m3) : '',
        places_count: load.places_count ? String(load.places_count) : '',
        body_type: load.body_type ?? '',
        loading_type: load.loading_type ?? '',
        temperature_mode: load.temperature_mode ?? '',
        price: load.price ? String(load.price) : '',
        price_with_vat: Boolean(load.price_with_vat),
        payment_type: load.payment_type ?? 'negotiable',
        payment_terms: load.payment_terms ?? '',
        contact_name: load.contact_name ?? '',
        contact_phone: load.contact_phone ?? '',
        contact_email: load.contact_email ?? '',
        cargo_photo: null as File | null,
        is_urgent: load.is_urgent,
    });

    const submit: FormEventHandler = (event) => {
        event.preventDefault();
        post(route('loads.update', load.id), { forceFormData: true });
    };

    const addressFields = ['loading_city', 'loading_address', 'unloading_city', 'unloading_address'];

    const applySuggestion = (key: string, suggestion: AddressSuggestion) => {
        if (!suggestion.lat || !suggestion.lng) {
            return;
        }

        if (key.startsWith('loading_')) {
            setData('loading_lat', String(suggestion.lat));
            setData('loading_lng', String(suggestion.lng));
        } else if (key.startsWith('unloading_')) {
            setData('unloading_lat', String(suggestion.lat));
            setData('unloading_lng', String(suggestion.lng));
        }
    };

    return (
        <AuthenticatedLayout breadcrumbs={[{ title: t('common.loads'), href: route('loads.index') }, { title: load.title, href: route('loads.show', load.id) }, { title: t('loads.breadcrumb_edit') }]}>
            <Head title={`${t('loads.edit_title')}: ${load.title}`} />
            <form onSubmit={submit} className="mx-auto grid max-w-6xl gap-5 px-4 py-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <h1 className="text-2xl font-semibold">{t('loads.edit_title')}</h1>
                    <Button asChild variant="secondary">
                        <Link href={route('loads.show', load.id)}>{t('common.back_to_card')}</Link>
                    </Button>
                </div>

                <div className="rounded-md border border-amber-300 bg-amber-50 p-4 text-sm leading-6 text-amber-950">{disclaimer}</div>

                <div className="grid gap-4 md:grid-cols-2">
                    {[
                        'title',
                        'loading_city',
                        'loading_region',
                        'loading_address',
                        'unloading_city',
                        'unloading_region',
                        'unloading_address',
                        'loading_date',
                        'loading_time_from',
                        'loading_time_to',
                        'unloading_date',
                        'unloading_time_from',
                        'unloading_time_to',
                        'weight_kg',
                        'volume_m3',
                        'places_count',
                        'temperature_mode',
                        'price',
                        'payment_terms',
                        'contact_name',
                        'contact_phone',
                        'contact_email',
                    ].map((key) => (
                        <div key={key} className="grid gap-2">
                            <Label htmlFor={key}>{t(`loads.fields.${key}`)}</Label>
                            {addressFields.includes(key) ? (
                                <AddressSuggestInput
                                    id={key}
                                    value={String(data[key as keyof typeof data])}
                                    required={['loading_city', 'unloading_city'].includes(key)}
                                    onChange={(value) => setData(key as keyof typeof data, value)}
                                    onSelect={(suggestion) => applySuggestion(key, suggestion)}
                                    valueFromSuggestion={(suggestion) => key.includes('_address')
                                        ? suggestion.address || suggestion.title
                                        : suggestion.city || suggestion.title}
                                />
                            ) : (
                                <Input
                                    id={key}
                                    type={key.includes('date') ? 'date' : key.includes('time') ? 'time' : 'text'}
                                    value={String(data[key as keyof typeof data])}
                                    onChange={(event) => setData(key as keyof typeof data, event.target.value)}
                                    required={['title', 'loading_city', 'unloading_city'].includes(key)}
                                />
                            )}
                            <InputError message={errors[key as keyof typeof errors]} />
                        </div>
                    ))}
                    <SelectField id="cargo_type" label={t('loads.fields.cargo_type')} value={data.cargo_type} options={options.cargo_types} onChange={(value) => setData('cargo_type', value)} error={errors.cargo_type} />
                    <SelectField id="body_type" label={t('loads.fields.body_type')} value={data.body_type} options={options.body_types} onChange={(value) => setData('body_type', value)} error={errors.body_type} />
                    <SelectField id="loading_type" label={t('loads.fields.loading_type')} value={data.loading_type} options={options.loading_types} onChange={(value) => setData('loading_type', value)} error={errors.loading_type} />
                    <div className="grid gap-2">
                        <Label htmlFor="payment_type">{t('common.payment')}</Label>
                        <select
                            id="payment_type"
                            value={data.payment_type}
                            onChange={(event) => setData('payment_type', event.target.value)}
                            className="rounded-md border bg-background px-3 py-2 text-sm"
                        >
                            {Object.entries(options.payment_types).map(([value, label]) => (
                                <option key={value} value={value}>{label}</option>
                            ))}
                        </select>
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="cargo_photo">Фото груза</Label>
                        {load.cargo_photo_url && <img src={load.cargo_photo_url} alt="" className="h-28 w-full rounded-md object-cover" />}
                        <Input id="cargo_photo" type="file" accept="image/*" onChange={(event) => setData('cargo_photo', event.target.files?.[0] ?? null)} />
                        <InputError message={errors.cargo_photo} />
                    </div>
                    <label className="flex items-center gap-2 text-sm">
                        <input type="checkbox" checked={data.price_with_vat} onChange={(event) => setData('price_with_vat', event.target.checked)} />
                        {t('loads.fields.price_with_vat')}
                    </label>
                    <label className="flex items-center gap-2 text-sm">
                        <input type="checkbox" checked={data.is_urgent} onChange={(event) => setData('is_urgent', event.target.checked)} />
                        {t('loads.fields.urgent')}
                    </label>
                    <div className="grid gap-2 md:col-span-2">
                        <Label htmlFor="cargo_description">{t('loads.fields.cargo_description')}</Label>
                        <textarea
                            id="cargo_description"
                            value={data.cargo_description}
                            onChange={(event) => setData('cargo_description', event.target.value)}
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
