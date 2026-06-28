import InputError from '@/Components/InputError';
import { AddressSuggestInput, type AddressSuggestion } from '@/Components/Freight/AddressSuggestInput';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useFreightTranslation } from '@/hooks/useFreightTranslation';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

type LoadForm = {
    title: string;
    cargo_type: string;
    cargo_description: string;
    loading_city: string;
    loading_region: string;
    loading_address: string;
    loading_lat: string;
    loading_lng: string;
    unloading_city: string;
    unloading_region: string;
    unloading_address: string;
    unloading_lat: string;
    unloading_lng: string;
    loading_date: string;
    loading_time_from: string;
    loading_time_to: string;
    unloading_date: string;
    unloading_time_from: string;
    unloading_time_to: string;
    weight_kg: string;
    volume_m3: string;
    places_count: string;
    body_type: string;
    loading_type: string;
    temperature_mode: string;
    price: string;
    price_with_vat: boolean;
    payment_type: string;
    payment_terms: string;
    contact_name: string;
    contact_phone: string;
    contact_email: string;
    cargo_photo: File | null;
    is_urgent: boolean;
    publish: boolean;
};

type Options = {
    body_types: Record<string, string>;
    cargo_types: Record<string, string>;
    loading_types: Record<string, string>;
    payment_types: Record<string, string>;
};

export default function Create({ disclaimer, options }: { disclaimer: string; options: Options }) {
    const t = useFreightTranslation();
    const { data, setData, post, processing, errors } = useForm<LoadForm>({
        title: '',
        cargo_type: '',
        cargo_description: '',
        loading_city: '',
        loading_region: '',
        loading_address: '',
        loading_lat: '',
        loading_lng: '',
        unloading_city: '',
        unloading_region: '',
        unloading_address: '',
        unloading_lat: '',
        unloading_lng: '',
        loading_date: '',
        loading_time_from: '',
        loading_time_to: '',
        unloading_date: '',
        unloading_time_from: '',
        unloading_time_to: '',
        weight_kg: '',
        volume_m3: '',
        places_count: '',
        body_type: '',
        loading_type: '',
        temperature_mode: '',
        price: '',
        price_with_vat: false,
        payment_type: 'negotiable',
        payment_terms: '',
        contact_name: '',
        contact_phone: '',
        contact_email: '',
        cargo_photo: null,
        is_urgent: false,
        publish: true,
    });

    const submit: FormEventHandler = (event) => {
        event.preventDefault();
        post(route('loads.store'));
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
        <AuthenticatedLayout breadcrumbs={[{ title: t('loads.breadcrumb_create') }]}>
            <Head title={t('loads.create_title')} />
            <form onSubmit={submit} className="mx-auto grid max-w-6xl gap-5 px-4 py-6">
                <h1 className="text-2xl font-semibold">{t('loads.create_title')}</h1>
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
                    <label className="flex items-center gap-2 text-sm">
                        <input type="checkbox" checked={data.publish} onChange={(event) => setData('publish', event.target.checked)} />
                        {t('loads.fields.publish_now')}
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
                <Button className="w-fit" disabled={processing}>{t('loads.save')}</Button>
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
