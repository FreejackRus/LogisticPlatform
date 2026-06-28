import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { useFreightTranslation } from '@/hooks/useFreightTranslation';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { formatDateRange, formatDateTime } from '@/lib/utils';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { ChevronDown, ChevronRight, FileText, MapPinned, Phone } from 'lucide-react';
import QRCode from 'qrcode';
import { FormEventHandler, ReactNode, useEffect, useMemo, useState } from 'react';

type Bid = {
    id: number;
    carrier_id: number;
    status: string;
    comment?: string;
    carrier?: { name: string; company?: { name?: string; phone?: string; email?: string } };
    vehicle?: { id: number; title: string };
    contract_accepted_at?: string | null;
    contract_signed_at?: string | null;
    carrier_cargo_photo_url?: string | null;
    can_upload_carrier_cargo_photo?: boolean;
};

type DeliveryEvent = {
    id: number;
    type: string;
    note?: string | null;
    created_at: string;
    actor?: { name?: string | null; role?: string | null };
};

type Load = {
    id: number;
    title: string;
    cargo_type?: string;
    cargo_description?: string;
    loading_city: string;
    unloading_city: string;
    body_type?: string;
    weight_kg?: number;
    volume_m3?: number;
    price?: number;
    payment_type: string;
    status: string;
    delivery_stage?: string | null;
    loading_region?: string;
    loading_address?: string;
    unloading_region?: string;
    unloading_address?: string;
    loading_date?: string;
    unloading_date?: string;
    places_count?: number;
    loading_type?: string;
    temperature_mode?: string;
    payment_terms?: string;
    cargo_photo_url?: string | null;
    contract_url?: string | null;
    delivery_confirmation?: {
        token?: string;
        code?: string;
        url?: string;
        confirmed_at?: string | null;
    };
    contact_name?: string;
    contact_phone?: string;
    contact_email?: string;
    company?: { name?: string; phone?: string; email?: string; verification_status?: string };
    bids?: Bid[];
    delivery_events?: DeliveryEvent[];
    can_update_delivery?: boolean;
    delivery_event_options?: string[];
};

type Props = {
    load: Load;
    disclaimer: string;
    contractText: string;
    canSeeContacts: boolean;
    canBid: boolean;
    canManage: boolean;
    canPublish: boolean;
    canCancel: boolean;
    canComplete: boolean;
    isDispatcher: boolean;
    routeToLoadUrl?: string | null;
    carrierVehicles: { id: number; title: string; registration_number?: string }[];
};

const statusTone: Record<string, string> = {
    draft: 'border-slate-200 bg-slate-50 text-slate-700',
    active: 'border-blue-200 bg-blue-50 text-blue-800',
    in_progress: 'border-emerald-200 bg-emerald-50 text-emerald-800',
    completed: 'border-slate-200 bg-slate-50 text-slate-700',
    cancelled: 'border-rose-200 bg-rose-50 text-rose-800',
};

export default function Show({
    load,
    disclaimer,
    contractText,
    canSeeContacts,
    canBid,
    canManage,
    canPublish,
    canCancel,
    canComplete,
    isDispatcher,
    routeToLoadUrl,
    carrierVehicles,
}: Props) {
    const t = useFreightTranslation();
    const [qrDataUrl, setQrDataUrl] = useState<string | null>(null);
    const [openSections, setOpenSections] = useState<Record<string, boolean>>(() => ({
        overview: true,
        route: true,
        workflow: Boolean(load.can_update_delivery || load.delivery_events?.length),
        confirmation: Boolean(load.status === 'in_progress' && load.delivery_confirmation),
        bid: canBid,
        dispatcher: isDispatcher,
        responses: canManage || Boolean(load.bids?.length && load.status !== 'active'),
        legal: false,
    }));
    const { data, setData, post, processing, errors } = useForm<{
        comment: string;
        vehicle_id: string;
        contract_accepted: boolean;
        carrier_cargo_photo: File | null;
    }>({
        comment: '',
        vehicle_id: '',
        contract_accepted: false,
        carrier_cargo_photo: null as File | null,
    });
    const completeForm = useForm({
        delivery_confirmation: new URLSearchParams(window.location.search).get('confirm') ?? '',
    });
    const deliveryForm = useForm({
        type: load.delivery_event_options?.[0] ?? '',
        note: '',
    });

    const currentStage = load.delivery_stage
        ? t(`delivery_events.${load.delivery_stage}`)
        : t('carrier_deliveries.stage_not_set');
    const price = load.price
        ? t('loads.price_rub', { price: load.price.toLocaleString('ru-RU') })
        : t('loads.negotiable_price');
    const paymentType = load.payment_type
        ? t(`loads.payment_types.${load.payment_type}`)
        : t('common.not_specified');
    const dates = formatDateRange(load.loading_date, load.unloading_date, t('common.not_specified'));
    const bidsCount = load.bids?.length ?? 0;

    const quickFacts = useMemo(() => [
        { label: t('common.fixed_price'), value: price },
        { label: t('carrier_deliveries.dates'), value: dates },
        { label: t('loads.fields.body_type'), value: load.body_type || t('common.not_specified') },
        { label: t('loads.responses'), value: t('loads.response_count', { count: bidsCount }) },
    ], [bidsCount, dates, load.body_type, price, t]);

    const setSection = (section: string) => {
        setOpenSections((current) => ({ ...current, [section]: !current[section] }));
    };

    const submitBid: FormEventHandler = (event) => {
        event.preventDefault();
        post(route('bids.store', load.id), { forceFormData: true });
    };

    const completeLoad: FormEventHandler = (event) => {
        event.preventDefault();
        completeForm.patch(route('loads.complete', load.id));
    };

    const submitDeliveryEvent: FormEventHandler = (event) => {
        event.preventDefault();
        deliveryForm.post(route('loads.delivery-events.store', load.id), {
            preserveScroll: true,
            onSuccess: () => deliveryForm.reset('note'),
        });
    };

    useEffect(() => {
        if (!load.delivery_confirmation?.url) {
            setQrDataUrl(null);
            return;
        }

        QRCode.toDataURL(load.delivery_confirmation.url, {
            margin: 1,
            width: 220,
        }).then(setQrDataUrl).catch(() => setQrDataUrl(null));
    }, [load.delivery_confirmation?.url]);

    return (
        <AuthenticatedLayout breadcrumbs={[{ title: t('common.loads'), href: route('loads.index') }, { title: load.title }]}>
            <Head title={load.title} />
            <div className="mx-auto grid max-w-6xl gap-5 px-3 py-4 sm:px-4 sm:py-6">
                <div className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-start">
                    <div>
                        <div className="flex flex-wrap items-center gap-2">
                            <Badge className={statusTone[load.status] ?? undefined} variant="outline">
                                {t(`carrier_deliveries.statuses.${load.status}`) || load.status}
                            </Badge>
                            {load.delivery_stage && <span className="text-sm text-emerald-700">{currentStage}</span>}
                        </div>
                        <h1 className="mt-2 text-2xl font-semibold leading-tight">{load.title}</h1>
                        <p className="mt-1 text-muted-foreground">{load.loading_city} - {load.unloading_city}</p>
                    </div>
                    <div className="grid grid-cols-2 gap-2 sm:flex sm:flex-wrap">
                        {load.contract_url && (
                            <Button asChild type="button" variant="secondary">
                                <a href={load.contract_url}>
                                    <FileText className="size-4" />
                                    {t('carrier_deliveries.contract')}
                                </a>
                            </Button>
                        )}
                        {routeToLoadUrl && (
                            <Button asChild type="button">
                                <Link href={routeToLoadUrl}>
                                    <MapPinned className="size-4" />
                                    {t('loads.build_route')}
                                </Link>
                            </Button>
                        )}
                        {canManage && (
                            <Button asChild type="button" variant="secondary">
                                <Link href={route('loads.edit', load.id)}>{t('common.edit')}</Link>
                            </Button>
                        )}
                    </div>
                </div>

                <section className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    {quickFacts.map((fact) => (
                        <Info key={fact.label} label={fact.label} value={fact.value} />
                    ))}
                </section>

                {load.cargo_photo_url && (
                    <img src={load.cargo_photo_url} alt="" className="max-h-[360px] w-full rounded-md object-cover" />
                )}

                <CollapsibleSection
                    title={t('loads.card_sections.overview')}
                    subtitle={t('loads.card_sections.overview_hint')}
                    open={openSections.overview}
                    onToggle={() => setSection('overview')}
                >
                    <div className="grid gap-4 lg:grid-cols-3">
                        <Panel title={t('common.load')}>
                            <Line label={t('loads.fields.cargo_type')} value={load.cargo_type} />
                            <Line label={t('loads.fields.body_type')} value={load.body_type} />
                            <Line label={t('loads.fields.weight_kg')} value={load.weight_kg ? `${load.weight_kg} кг` : null} />
                            <Line label={t('loads.fields.volume_m3')} value={load.volume_m3 ? `${load.volume_m3} м3` : null} />
                            <Line label={t('loads.fields.places_count')} value={load.places_count} />
                            <Line label={t('loads.fields.loading_type')} value={load.loading_type} />
                            <Line label={t('loads.fields.temperature_mode')} value={load.temperature_mode} />
                        </Panel>
                        <Panel title={t('carrier_deliveries.payment_block')}>
                            <Line label={t('common.fixed_price')} value={price} />
                            <Line label={t('loads.fields.payment_type')} value={paymentType} />
                            <Line label={t('loads.fields.payment_terms')} value={load.payment_terms} />
                        </Panel>
                        <Panel title={t('common.contacts')}>
                            {canSeeContacts ? (
                                <>
                                    <Line label={t('loads.fields.contact_name')} value={load.contact_name || load.company?.name} />
                                    <Line label={t('loads.fields.contact_phone')} value={load.contact_phone || load.company?.phone} />
                                    <Line label={t('loads.fields.contact_email')} value={load.contact_email || load.company?.email} />
                                    {(load.contact_phone || load.company?.phone) && (
                                        <Button asChild className="mt-2 w-fit" size="sm" variant="secondary">
                                            <a href={`tel:${load.contact_phone || load.company?.phone}`}>
                                                <Phone className="size-4" />
                                                {t('carrier_deliveries.call')}
                                            </a>
                                        </Button>
                                    )}
                                </>
                            ) : (
                                <p className="text-sm text-muted-foreground">{t('loads.contacts_registered_only')}</p>
                            )}
                        </Panel>
                    </div>
                    {load.cargo_description && (
                        <div className="rounded-md border p-4">
                            <h3 className="font-semibold">{t('loads.fields.cargo_description')}</h3>
                            <p className="mt-2 text-sm">{load.cargo_description}</p>
                        </div>
                    )}
                </CollapsibleSection>

                <CollapsibleSection
                    title={t('loads.card_sections.route')}
                    subtitle={dates}
                    open={openSections.route}
                    onToggle={() => setSection('route')}
                >
                    <div className="grid gap-4 lg:grid-cols-2">
                        <Panel title={t('loads.fields.loading_city')}>
                            <p className="text-sm">{[load.loading_city, load.loading_region, load.loading_address].filter(Boolean).join(', ')}</p>
                        </Panel>
                        <Panel title={t('loads.fields.unloading_city')}>
                            <p className="text-sm">{[load.unloading_city, load.unloading_region, load.unloading_address].filter(Boolean).join(', ')}</p>
                        </Panel>
                    </div>
                    {routeToLoadUrl && (
                        <div className="rounded-md border border-emerald-200 bg-emerald-50 p-4">
                            <div className="flex flex-wrap items-center justify-between gap-3">
                                <div>
                                    <h3 className="font-semibold text-emerald-950">{t('loads.route_available_title')}</h3>
                                    <p className="text-sm text-emerald-900">{t('loads.route_available_text')}</p>
                                </div>
                                <Button asChild type="button">
                                    <Link href={routeToLoadUrl}>{t('loads.build_route')}</Link>
                                </Button>
                            </div>
                        </div>
                    )}
                </CollapsibleSection>

                {canManage && (
                    <CollapsibleSection
                        title={t('loads.card_sections.management')}
                        subtitle={t('loads.card_sections.management_hint')}
                        open={openSections.management ?? true}
                        onToggle={() => setSection('management')}
                    >
                        <div className="flex flex-wrap gap-2">
                            <Button asChild type="button" variant="secondary">
                                <Link href={route('loads.edit', load.id)}>{t('common.edit')}</Link>
                            </Button>
                            {canPublish && <Button type="button" onClick={() => router.patch(route('loads.publish', load.id))}>{t('loads.publish')}</Button>}
                            {canCancel && <Button type="button" variant="destructive" onClick={() => router.patch(route('loads.cancel', load.id))}>{t('common.cancel')}</Button>}
                        </div>
                    </CollapsibleSection>
                )}

                {load.status === 'in_progress' && load.delivery_confirmation && (
                    <CollapsibleSection
                        title={t('carrier_deliveries.confirmation_title')}
                        subtitle={t('carrier_deliveries.confirmation_text')}
                        open={openSections.confirmation}
                        onToggle={() => setSection('confirmation')}
                    >
                        <div className="grid gap-4 rounded-md border border-emerald-200 bg-emerald-50 p-4 md:grid-cols-[auto_1fr]">
                            {qrDataUrl && <img src={qrDataUrl} alt="" className="h-44 w-44 rounded-md border bg-white p-2" />}
                            <div className="grid gap-3">
                                <div className="text-sm">
                                    {t('carrier_deliveries.confirmation_code')}: <span className="font-mono text-base font-semibold">{load.delivery_confirmation.code}</span>
                                </div>
                                {canComplete && (
                                    <form onSubmit={completeLoad} className="flex flex-col gap-2 sm:flex-row">
                                        <Input
                                            value={completeForm.data.delivery_confirmation}
                                            onChange={(event) => completeForm.setData('delivery_confirmation', event.target.value)}
                                            placeholder={t('loads.delivery_confirmation_placeholder')}
                                        />
                                        <Button disabled={completeForm.processing}>{t('loads.complete')}</Button>
                                        {completeForm.errors.delivery_confirmation && (
                                            <p className="text-sm text-destructive">{completeForm.errors.delivery_confirmation}</p>
                                        )}
                                    </form>
                                )}
                                {load.delivery_confirmation.confirmed_at && (
                                    <p className="text-sm text-emerald-700">
                                        {t('loads.delivery_confirmed_at', { date: load.delivery_confirmation.confirmed_at })}
                                    </p>
                                )}
                            </div>
                        </div>
                    </CollapsibleSection>
                )}

                {(load.can_update_delivery || Boolean(load.delivery_events?.length)) && (
                    <CollapsibleSection
                        title={t('carrier_deliveries.events_title')}
                        subtitle={`${t('carrier_deliveries.stage')}: ${currentStage}`}
                        open={openSections.workflow}
                        onToggle={() => setSection('workflow')}
                    >
                        {load.can_update_delivery && load.delivery_event_options && load.delivery_event_options.length > 0 && (
                            <form onSubmit={submitDeliveryEvent} className="grid gap-2 rounded-md border p-3 md:grid-cols-[1fr_2fr_auto]">
                                <select
                                    value={deliveryForm.data.type}
                                    onChange={(event) => deliveryForm.setData('type', event.target.value)}
                                    className="rounded-md border bg-background px-3 py-2 text-sm"
                                >
                                    {load.delivery_event_options.map((option) => (
                                        <option key={option} value={option}>
                                            {t(`delivery_events.${option}`)}
                                        </option>
                                    ))}
                                </select>
                                <Input
                                    value={deliveryForm.data.note}
                                    onChange={(event) => deliveryForm.setData('note', event.target.value)}
                                    placeholder={t('carrier_deliveries.event_note')}
                                />
                                <Button disabled={deliveryForm.processing || !deliveryForm.data.type}>{t('carrier_deliveries.update_stage')}</Button>
                                {deliveryForm.errors.type && <p className="text-sm text-destructive">{deliveryForm.errors.type}</p>}
                                {deliveryForm.errors.note && <p className="text-sm text-destructive">{deliveryForm.errors.note}</p>}
                            </form>
                        )}
                        <EventList events={load.delivery_events ?? []} />
                    </CollapsibleSection>
                )}

                {canBid && (
                    <CollapsibleSection
                        title={t('loads.respond')}
                        subtitle={contractText}
                        open={openSections.bid}
                        onToggle={() => setSection('bid')}
                    >
                        <form onSubmit={submitBid} className="grid gap-3 rounded-md border p-4 md:grid-cols-[1fr_2fr_auto]">
                            <select
                                value={data.vehicle_id}
                                onChange={(event) => setData('vehicle_id', event.target.value)}
                                className="rounded-md border bg-background px-3 py-2 text-sm"
                                required
                            >
                                <option value="">{t('common.transport')}</option>
                                {carrierVehicles.map((vehicle) => (
                                    <option key={vehicle.id} value={vehicle.id}>
                                        {[vehicle.title, vehicle.registration_number].filter(Boolean).join(' · ')}
                                    </option>
                                ))}
                            </select>
                            <Input value={data.comment} onChange={(event) => setData('comment', event.target.value)} placeholder={t('loads.response_comment')} />
                            <Button disabled={processing || !data.vehicle_id}>{t('loads.respond')}</Button>
                            <div className="grid gap-2 md:col-span-3">
                                <label className="flex items-start gap-2 text-sm">
                                    <input
                                        type="checkbox"
                                        checked={data.contract_accepted}
                                        onChange={(event) => setData('contract_accepted', event.target.checked)}
                                        className="mt-1"
                                    />
                                    <span>{contractText}</span>
                                </label>
                                <Input
                                    type="file"
                                    accept="image/*"
                                    onChange={(event) => setData('carrier_cargo_photo', event.target.files?.[0] ?? null)}
                                />
                                {errors.vehicle_id && <p className="text-sm text-destructive">{errors.vehicle_id}</p>}
                                {errors.contract_accepted && <p className="text-sm text-destructive">{errors.contract_accepted}</p>}
                                {errors.carrier_cargo_photo && <p className="text-sm text-destructive">{errors.carrier_cargo_photo}</p>}
                            </div>
                            {errors.comment && <p className="text-sm text-destructive">{errors.comment}</p>}
                        </form>
                    </CollapsibleSection>
                )}

                {isDispatcher && (
                    <CollapsibleSection
                        title={t('loads.card_sections.dispatcher')}
                        subtitle={t('loads.card_sections.dispatcher_hint')}
                        open={openSections.dispatcher}
                        onToggle={() => setSection('dispatcher')}
                    >
                        <div className="flex flex-wrap gap-2">
                            <Button asChild variant="secondary">
                                <Link href={route('dispatcher.loads.nearest-carriers', load.id)}>{t('loads.find_nearest_carriers')}</Link>
                            </Button>
                            <Button asChild variant="secondary">
                                <Link href={route('dispatcher.connections.index')}>{t('loads.manual_connections')}</Link>
                            </Button>
                        </div>
                    </CollapsibleSection>
                )}

                {load.bids && load.bids.length > 0 && (
                    <CollapsibleSection
                        title={t('loads.responses')}
                        subtitle={t('loads.response_count', { count: load.bids.length })}
                        open={openSections.responses}
                        onToggle={() => setSection('responses')}
                    >
                        <div className="grid gap-3">
                            {load.bids.map((bid) => (
                                <BidCard key={bid.id} bid={bid} canManage={canManage} />
                            ))}
                        </div>
                    </CollapsibleSection>
                )}

                <CollapsibleSection
                    title={t('loads.card_sections.legal')}
                    subtitle={t('loads.card_sections.legal_hint')}
                    open={openSections.legal}
                    onToggle={() => setSection('legal')}
                >
                    <div className="rounded-md border border-amber-300 bg-amber-50 p-4 text-sm leading-6 text-amber-950">{disclaimer}</div>
                </CollapsibleSection>
            </div>
        </AuthenticatedLayout>
    );
}

function CollapsibleSection({
    title,
    subtitle,
    open,
    onToggle,
    children,
}: {
    title: string;
    subtitle?: string;
    open: boolean;
    onToggle: () => void;
    children: ReactNode;
}) {
    return (
        <section className="overflow-hidden rounded-md border bg-background">
            <button
                type="button"
                onClick={onToggle}
                className="flex w-full items-start justify-between gap-3 p-4 text-left hover:bg-muted/40"
                aria-expanded={open}
            >
                <span>
                    <span className="block font-semibold">{title}</span>
                    {subtitle && <span className="mt-1 block text-sm text-muted-foreground">{subtitle}</span>}
                </span>
                {open ? <ChevronDown className="mt-1 size-4 shrink-0" /> : <ChevronRight className="mt-1 size-4 shrink-0" />}
            </button>
            {open && <div className="grid gap-4 border-t p-4">{children}</div>}
        </section>
    );
}

function Panel({ title, children }: { title: string; children: ReactNode }) {
    return (
        <div className="rounded-md border p-4">
            <h3 className="font-semibold">{title}</h3>
            <div className="mt-3 grid gap-2">{children}</div>
        </div>
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

function Line({ label, value }: { label: string; value?: ReactNode }) {
    const t = useFreightTranslation();

    return (
        <div>
            <div className="text-xs text-muted-foreground">{label}</div>
            <div className="text-sm">{value || t('common.not_specified')}</div>
        </div>
    );
}

function EventList({ events }: { events: DeliveryEvent[] }) {
    const t = useFreightTranslation();

    if (events.length === 0) {
        return <p className="text-sm text-muted-foreground">{t('carrier_deliveries.events_empty')}</p>;
    }

    return (
        <div className="grid gap-2">
            {events.map((event) => (
                <div key={event.id} className="rounded-md border bg-muted/20 p-3">
                    <div className="flex flex-wrap items-center justify-between gap-2">
                        <p className="font-medium">{t(`delivery_events.${event.type}`)}</p>
                        <p className="text-xs text-muted-foreground">{formatDateTime(event.created_at)}</p>
                    </div>
                    <p className="text-xs text-muted-foreground">
                        {[event.actor?.name, event.actor?.role].filter(Boolean).join(' · ') || t('common.not_specified')}
                    </p>
                    {event.note && <p className="mt-2 text-sm">{event.note}</p>}
                </div>
            ))}
        </div>
    );
}

function BidCard({ bid, canManage }: { bid: Bid; canManage: boolean }) {
    const t = useFreightTranslation();

    return (
        <div className="flex flex-wrap items-center justify-between gap-3 rounded-md border p-4">
            <div>
                <p className="font-medium">{bid.carrier?.company?.name || bid.carrier?.name}</p>
                <p className="text-sm text-muted-foreground">
                    {bid.vehicle?.id ? (
                        <Link className="underline" href={route('vehicles.show', bid.vehicle.id)}>
                            {bid.vehicle.title}
                        </Link>
                    ) : (
                        bid.vehicle?.title || t('common.transport')
                    )}{' '}
                    · {t(`loads.response_statuses.${bid.status}`) || bid.status}
                </p>
                {bid.comment && <p className="text-sm">{bid.comment}</p>}
                {bid.contract_signed_at && <p className="text-xs text-emerald-700">{t('loads.contract_signed')}</p>}
                {bid.carrier_cargo_photo_url && (
                    <img src={bid.carrier_cargo_photo_url} alt="" className="mt-2 h-28 w-44 rounded-md object-cover" />
                )}
                {bid.can_upload_carrier_cargo_photo && <CarrierCargoPhotoForm bid={bid} />}
            </div>
            {canManage && bid.status === 'pending' && (
                <Button type="button" onClick={() => router.patch(route('bids.accept', bid.id))}>
                    {t('loads.choose_carrier')}
                </Button>
            )}
        </div>
    );
}

function CarrierCargoPhotoForm({ bid }: { bid: Bid }) {
    const t = useFreightTranslation();
    const form = useForm<{ carrier_cargo_photo: File | null }>({
        carrier_cargo_photo: null,
    });

    const submit: FormEventHandler = (event) => {
        event.preventDefault();
        form.post(route('bids.carrier-photo', bid.id), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => form.reset('carrier_cargo_photo'),
        });
    };

    return (
        <form onSubmit={submit} className="mt-3 grid gap-2 rounded-md border bg-muted/30 p-3">
            <p className="text-sm font-medium">{t('carrier_deliveries.carrier_photo')}</p>
            <p className="text-xs text-muted-foreground">{t('carrier_deliveries.carrier_photo_empty')}</p>
            <div className="flex flex-col gap-2 sm:flex-row">
                <Input
                    type="file"
                    accept="image/*"
                    onChange={(event) => form.setData('carrier_cargo_photo', event.target.files?.[0] ?? null)}
                />
                <Button disabled={form.processing || !form.data.carrier_cargo_photo}>{t('carrier_deliveries.upload_photo')}</Button>
            </div>
            {form.errors.carrier_cargo_photo && <p className="text-sm text-destructive">{form.errors.carrier_cargo_photo}</p>}
        </form>
    );
}
