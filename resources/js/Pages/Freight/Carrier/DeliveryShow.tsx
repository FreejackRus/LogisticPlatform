import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { useFreightTranslation } from '@/hooks/useFreightTranslation';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { formatDateRange, formatDateTime } from '@/lib/utils';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { Camera, CheckCircle2, FileText, MapPinned, Phone, Upload } from 'lucide-react';
import QRCode from 'qrcode';
import { FormEventHandler, useEffect, useState } from 'react';

type DeliveryEvent = {
    id: number;
    type: string;
    note?: string | null;
    created_at: string;
    actor?: { name?: string | null; role?: string | null };
};

type Delivery = {
    bid_id: number;
    load: {
        id: number;
        title: string;
        status: string;
        delivery_stage?: string | null;
        loading_city: string;
        unloading_city: string;
        loading_region?: string | null;
        loading_address?: string | null;
        unloading_region?: string | null;
        unloading_address?: string | null;
        loading_date?: string | null;
        unloading_date?: string | null;
        cargo_type?: string | null;
        cargo_description?: string | null;
        body_type?: string | null;
        weight_kg?: number | null;
        volume_m3?: number | null;
        places_count?: number | null;
        loading_type?: string | null;
        temperature_mode?: string | null;
        price?: number | null;
        payment_type?: string | null;
        payment_terms?: string | null;
        contact_name?: string | null;
        contact_phone?: string | null;
        contact_email?: string | null;
        cargo_photo_url?: string | null;
        delivery_confirmation?: {
            code?: string | null;
            url?: string | null;
            confirmed_at?: string | null;
        };
        url: string;
        contract_url: string;
        route_url: string;
        event_url: string;
    };
    vehicle?: {
        id: number;
        title: string;
        registration_number?: string | null;
    } | null;
    carrier_cargo_photo_url?: string | null;
    carrier_photo_url: string;
    can_update_delivery: boolean;
    can_upload_carrier_cargo_photo: boolean;
    delivery_event_options: string[];
    next_delivery_event?: string | null;
    latest_event?: DeliveryEvent | null;
    events: DeliveryEvent[];
};

type Props = {
    delivery: Delivery;
};

type DeliveryEventForm = {
    type: string;
    note: string;
    lat: number | null;
    lng: number | null;
};

type CurrentLocation = {
    lat: number;
    lng: number;
};

const statusTone: Record<string, string> = {
    in_progress: 'border-emerald-200 bg-emerald-50 text-emerald-800',
    completed: 'border-slate-200 bg-slate-50 text-slate-700',
};

export default function DeliveryShow({ delivery }: Props) {
    const t = useFreightTranslation();
    const [qrDataUrl, setQrDataUrl] = useState<string | null>(null);
    const [locationStatus, setLocationStatus] = useState<string | null>(null);
    const eventForm = useForm<DeliveryEventForm>({
        type: delivery.delivery_event_options[0] ?? '',
        note: '',
        lat: null,
        lng: null,
    });
    const photoForm = useForm<{ carrier_cargo_photo: File | null }>({
        carrier_cargo_photo: null,
    });
    const currentStage = delivery.load.delivery_stage
        ? t(`delivery_events.${delivery.load.delivery_stage}`)
        : t('carrier_deliveries.stage_not_set');
    const dates = formatDateRange(delivery.load.loading_date, delivery.load.unloading_date, t('common.not_specified'));
    const price = delivery.load.price
        ? t('loads.price_rub', { price: delivery.load.price.toLocaleString('ru-RU') })
        : t('loads.negotiable_price');
    const eventRequiresCarrierPhoto = (type: string) => type === 'loaded' && !delivery.carrier_cargo_photo_url;
    const isCurrentEventBlocked = eventRequiresCarrierPhoto(eventForm.data.type);
    const isNextEventBlocked = delivery.next_delivery_event ? eventRequiresCarrierPhoto(delivery.next_delivery_event) : false;
    const paymentType = delivery.load.payment_type
        ? t(`loads.payment_types.${delivery.load.payment_type}`)
        : null;
    const vehicle = [delivery.vehicle?.title, delivery.vehicle?.registration_number]
        .filter(Boolean)
        .join(' · ') || t('common.not_specified');

    useEffect(() => {
        if (!delivery.load.delivery_confirmation?.url) {
            setQrDataUrl(null);
            return;
        }

        QRCode.toDataURL(delivery.load.delivery_confirmation.url, {
            margin: 1,
            width: 220,
        }).then(setQrDataUrl).catch(() => setQrDataUrl(null));
    }, [delivery.load.delivery_confirmation?.url]);

    const withCurrentLocation = (callback: (location: CurrentLocation | null) => void) => {
        if (!navigator.geolocation) {
            setLocationStatus(t('carrier_deliveries.location_unavailable'));
            callback(null);
            return;
        }

        setLocationStatus(t('carrier_deliveries.location_requesting'));

        navigator.geolocation.getCurrentPosition(
            ({ coords }) => {
                setLocationStatus(t('carrier_deliveries.location_attached'));
                callback({
                    lat: coords.latitude,
                    lng: coords.longitude,
                });
            },
            () => {
                setLocationStatus(t('carrier_deliveries.location_unavailable'));
                callback(null);
            },
            {
                enableHighAccuracy: true,
                maximumAge: 60000,
                timeout: 6000,
            },
        );
    };

    const postEvent = (payload: DeliveryEventForm, onSuccess?: () => void) => {
        eventForm.transform((data) => ({ ...data, ...payload }));
        eventForm.post(delivery.load.event_url, {
            preserveScroll: true,
            onSuccess,
            onFinish: () => eventForm.transform((data) => data),
        });
    };

    const submitEvent: FormEventHandler = (event) => {
        event.preventDefault();
        withCurrentLocation((location) => {
            postEvent(
                {
                    ...eventForm.data,
                    lat: location?.lat ?? null,
                    lng: location?.lng ?? null,
                },
                () => eventForm.reset('note'),
            );
        });
    };

    const submitNextEvent = () => {
        if (!delivery.next_delivery_event) {
            return;
        }

        withCurrentLocation((location) => {
            router.post(
                delivery.load.event_url,
                {
                    type: delivery.next_delivery_event,
                    lat: location?.lat ?? null,
                    lng: location?.lng ?? null,
                },
                { preserveScroll: true },
            );
        });
    };

    const submitPhoto: FormEventHandler = (event) => {
        event.preventDefault();
        photoForm.post(delivery.carrier_photo_url, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => photoForm.reset('carrier_cargo_photo'),
        });
    };

    return (
        <AuthenticatedLayout breadcrumbs={[
            { title: t('carrier_deliveries.title'), href: route('carrier.deliveries.index') },
            { title: delivery.load.title },
        ]}>
            <Head title={delivery.load.title} />
            <div className="mx-auto grid max-w-6xl gap-5 px-3 py-4 sm:px-4 sm:py-6">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <div className="flex flex-wrap items-center gap-2">
                            <Badge className={statusTone[delivery.load.status] ?? undefined} variant="outline">
                                {t(`carrier_deliveries.statuses.${delivery.load.status}`)}
                            </Badge>
                            <span className="text-sm text-muted-foreground">{currentStage}</span>
                        </div>
                        <h1 className="mt-2 text-2xl font-semibold">{delivery.load.title}</h1>
                        <p className="mt-1 text-muted-foreground">
                            {delivery.load.loading_city} - {delivery.load.unloading_city}
                        </p>
                    </div>
                    <div className="grid grid-cols-2 gap-2 sm:flex sm:flex-wrap">
                        {delivery.load.contact_phone && (
                            <Button asChild variant="secondary">
                                <a href={`tel:${delivery.load.contact_phone}`}>
                                    <Phone className="size-4" />
                                    {t('carrier_deliveries.call')}
                                </a>
                            </Button>
                        )}
                        {delivery.load.status === 'in_progress' && (
                            <Button asChild>
                                <Link href={delivery.load.route_url}>
                                    <MapPinned className="size-4" />
                                    {t('loads.build_route')}
                                </Link>
                            </Button>
                        )}
                        <Button asChild variant="secondary">
                            <a href={delivery.load.contract_url}>
                                <FileText className="size-4" />
                                {t('carrier_deliveries.contract')}
                            </a>
                        </Button>
                        <Button asChild variant="secondary">
                            <Link href={delivery.load.url}>{t('carrier_deliveries.open_load')}</Link>
                        </Button>
                    </div>
                </div>

                {delivery.load.cargo_photo_url && (
                    <img
                        src={delivery.load.cargo_photo_url}
                        alt=""
                        className="max-h-[360px] w-full rounded-md object-cover"
                    />
                )}

                <section className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <Info label={t('carrier_deliveries.stage')} value={currentStage} />
                    <Info label={t('common.transport')} value={vehicle} />
                    <Info label={t('common.fixed_price')} value={price} />
                    <Info label={t('carrier_deliveries.dates')} value={dates} />
                </section>

                <section className="grid gap-4 lg:grid-cols-3">
                    <div className="rounded-md border p-4">
                        <h2 className="font-semibold">{t('common.load')}</h2>
                        <dl className="mt-3 grid gap-2 text-sm">
                            <Line label={t('loads.fields.cargo_type')} value={delivery.load.cargo_type} />
                            <Line label={t('loads.fields.body_type')} value={delivery.load.body_type} />
                            <Line label={t('loads.fields.weight_kg')} value={delivery.load.weight_kg ? `${delivery.load.weight_kg} кг` : null} />
                            <Line label={t('loads.fields.volume_m3')} value={delivery.load.volume_m3 ? `${delivery.load.volume_m3} м3` : null} />
                            <Line label={t('loads.fields.places_count')} value={delivery.load.places_count} />
                            <Line label={t('loads.fields.loading_type')} value={delivery.load.loading_type} />
                            <Line label={t('loads.fields.temperature_mode')} value={delivery.load.temperature_mode} />
                        </dl>
                    </div>

                    <div className="rounded-md border p-4">
                        <h2 className="font-semibold">{t('carrier_deliveries.route_block')}</h2>
                        <div className="mt-3 grid gap-3 text-sm">
                            <div>
                                <p className="text-xs text-muted-foreground">{t('loads.fields.loading_city')}</p>
                                <p>{[delivery.load.loading_city, delivery.load.loading_region, delivery.load.loading_address].filter(Boolean).join(', ')}</p>
                            </div>
                            <div>
                                <p className="text-xs text-muted-foreground">{t('loads.fields.unloading_city')}</p>
                                <p>{[delivery.load.unloading_city, delivery.load.unloading_region, delivery.load.unloading_address].filter(Boolean).join(', ')}</p>
                            </div>
                        </div>
                    </div>

                    <div className="rounded-md border p-4">
                        <h2 className="font-semibold">{t('carrier_deliveries.shipper_contact')}</h2>
                        <div className="mt-3 grid gap-1 text-sm">
                            <p>{delivery.load.contact_name || t('common.not_specified')}</p>
                            {delivery.load.contact_phone && <a className="underline" href={`tel:${delivery.load.contact_phone}`}>{delivery.load.contact_phone}</a>}
                            {delivery.load.contact_email && <a className="underline" href={`mailto:${delivery.load.contact_email}`}>{delivery.load.contact_email}</a>}
                        </div>
                    </div>
                </section>

                {delivery.load.cargo_description && (
                    <section className="rounded-md border p-4">
                        <h2 className="font-semibold">{t('loads.fields.cargo_description')}</h2>
                        <p className="mt-2 text-sm">{delivery.load.cargo_description}</p>
                    </section>
                )}

                {delivery.can_update_delivery && delivery.delivery_event_options.length > 0 && (
                    <form onSubmit={submitEvent} className="grid gap-3 rounded-md border p-4">
                        {delivery.next_delivery_event && (
                            <div className="flex flex-wrap items-center justify-between gap-3 rounded-md bg-muted p-3">
                                <div>
                                    <p className="text-sm font-medium">Следующий этап</p>
                                    <p className="text-sm text-muted-foreground">
                                        {t(`delivery_events.${delivery.next_delivery_event}`)}
                                    </p>
                                </div>
                                <Button type="button" onClick={submitNextEvent} disabled={isNextEventBlocked}>
                                    <CheckCircle2 className="size-4" />
                                    Отметить
                                </Button>
                            </div>
                        )}
                        <div className="grid gap-2 lg:grid-cols-[1fr_2fr_auto]">
                        <select
                            value={eventForm.data.type}
                            onChange={(event) => eventForm.setData('type', event.target.value)}
                            className="min-h-10 rounded-md border bg-background px-3 py-2 text-sm"
                        >
                            {delivery.delivery_event_options.map((option) => (
                                <option key={option} value={option}>
                                    {t(`delivery_events.${option}`)}
                                </option>
                            ))}
                        </select>
                        <Input
                            value={eventForm.data.note}
                            onChange={(event) => eventForm.setData('note', event.target.value)}
                            placeholder={t('carrier_deliveries.event_note')}
                        />
                        <Button disabled={eventForm.processing || !eventForm.data.type || isCurrentEventBlocked}>
                            <CheckCircle2 className="size-4" />
                            {t('carrier_deliveries.update_stage')}
                        </Button>
                        </div>
                        {(isCurrentEventBlocked || isNextEventBlocked) && (
                            <p className="text-sm text-destructive">{t('carrier_deliveries.photo_required_before_loaded')}</p>
                        )}
                        {eventForm.errors.type && <p className="text-sm text-destructive">{eventForm.errors.type}</p>}
                        {eventForm.errors.note && <p className="text-sm text-destructive">{eventForm.errors.note}</p>}
                        {locationStatus && <p className="text-xs text-muted-foreground">{locationStatus}</p>}
                    </form>
                )}

                {delivery.load.status === 'in_progress' && delivery.load.delivery_confirmation && (
                    <section className="grid gap-3 rounded-md border border-emerald-200 bg-emerald-50 p-4 text-sm sm:grid-cols-[auto_1fr] sm:items-center">
                        {qrDataUrl && (
                            <img src={qrDataUrl} alt="" className="h-40 w-40 rounded-md border bg-white p-2" />
                        )}
                        <div>
                            <h2 className="font-semibold text-emerald-950">{t('carrier_deliveries.confirmation_title')}</h2>
                            <p className="mt-1 text-emerald-900">{t('carrier_deliveries.confirmation_text')}</p>
                            {delivery.load.delivery_confirmation.code && (
                                <p className="mt-2 text-emerald-950">
                                    {t('carrier_deliveries.confirmation_code')}: <span className="font-mono text-lg font-semibold">{delivery.load.delivery_confirmation.code}</span>
                                </p>
                            )}
                        </div>
                    </section>
                )}

                <section className="grid gap-4 lg:grid-cols-[1fr_1fr]">
                    <div className="rounded-md border p-4">
                        <div className="flex items-center gap-2 font-semibold">
                            <Camera className="size-4" />
                            {t('carrier_deliveries.carrier_photo')}
                        </div>
                        {delivery.carrier_cargo_photo_url ? (
                            <img src={delivery.carrier_cargo_photo_url} alt="" className="mt-3 h-36 w-56 rounded-md object-cover" />
                        ) : (
                            <p className="mt-2 text-sm text-muted-foreground">{t('carrier_deliveries.carrier_photo_empty')}</p>
                        )}
                        {delivery.can_upload_carrier_cargo_photo && (
                            <form onSubmit={submitPhoto} className="mt-3 flex flex-col gap-2 sm:flex-row">
                                <Input
                                    type="file"
                                    accept="image/*"
                                    capture="environment"
                                    onChange={(event) => photoForm.setData('carrier_cargo_photo', event.target.files?.[0] ?? null)}
                                />
                                <Button disabled={photoForm.processing || !photoForm.data.carrier_cargo_photo} variant="secondary">
                                    <Upload className="size-4" />
                                    {t('carrier_deliveries.upload_photo')}
                                </Button>
                                {photoForm.errors.carrier_cargo_photo && (
                                    <p className="text-sm text-destructive">{photoForm.errors.carrier_cargo_photo}</p>
                                )}
                            </form>
                        )}
                    </div>

                    <div className="rounded-md border p-4">
                        <h2 className="font-semibold">{t('carrier_deliveries.payment_block')}</h2>
                        <dl className="mt-3 grid gap-2 text-sm">
                            <Line label={t('common.fixed_price')} value={price} />
                            <Line label={t('loads.fields.payment_type')} value={paymentType} />
                            <Line label={t('loads.fields.payment_terms')} value={delivery.load.payment_terms} />
                        </dl>
                    </div>
                </section>

                <section className="grid gap-3 rounded-md border p-4">
                    <h2 className="font-semibold">{t('carrier_deliveries.events_title')}</h2>
                    {delivery.events.length === 0 ? (
                        <p className="text-sm text-muted-foreground">{t('carrier_deliveries.events_empty')}</p>
                    ) : (
                        <div className="grid gap-2">
                            {delivery.events.map((event) => (
                                <div key={event.id} className="rounded-md border bg-muted/20 p-3 text-sm">
                                    <div className="flex flex-wrap items-center justify-between gap-2">
                                        <p className="font-medium">{t(`delivery_events.${event.type}`)}</p>
                                        <p className="text-xs text-muted-foreground">{formatDateTime(event.created_at)}</p>
                                    </div>
                                    <p className="text-xs text-muted-foreground">
                                        {[event.actor?.name, event.actor?.role].filter(Boolean).join(' · ') || t('common.not_specified')}
                                    </p>
                                    {event.note && <p className="mt-2">{event.note}</p>}
                                </div>
                            ))}
                        </div>
                    )}
                </section>
            </div>
        </AuthenticatedLayout>
    );
}

function Info({ label, value }: { label: string; value: string }) {
    return (
        <div className="rounded-md border p-3">
            <p className="text-xs text-muted-foreground">{label}</p>
            <p className="font-medium">{value}</p>
        </div>
    );
}

function Line({ label, value }: { label: string; value?: string | number | null }) {
    const t = useFreightTranslation();

    return (
        <div className="grid gap-1">
            <dt className="text-xs text-muted-foreground">{label}</dt>
            <dd>{value || t('common.not_specified')}</dd>
        </div>
    );
}
