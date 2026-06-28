import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { formatDateRange, formatDateTime } from '@/lib/utils';
import { Head, Link, useForm } from '@inertiajs/react';
import { CheckCircle2, FileText, MessageSquareWarning, Phone, Truck } from 'lucide-react';
import { FormEventHandler, ReactNode } from 'react';

type DeliveryEvent = {
    id: number;
    type: string;
    note?: string | null;
    created_at?: string | null;
    actor?: {
        name?: string | null;
        role?: string | null;
    };
};

type Delivery = {
    load: {
        id: number;
        title: string;
        status: string;
        delivery_stage?: string | null;
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
        places_count?: number | null;
        price?: number | null;
        price_currency?: string | null;
        payment_type?: string | null;
        payment_terms?: string | null;
        cargo_photo_url?: string | null;
        completed_at?: string | null;
        completion_confirmed_at?: string | null;
        urls: {
            show: string;
            contract: string;
            complete: string;
            event: string;
        };
    };
    carrier: {
        bid_id: number;
        status: string;
        contract_accepted_at?: string | null;
        contract_signed_at?: string | null;
        carrier_cargo_photo_url?: string | null;
        contact: {
            name?: string | null;
            phone?: string | null;
            email?: string | null;
        };
        company: {
            name?: string | null;
            phone?: string | null;
            email?: string | null;
            verification_status?: string | null;
            rating?: number | null;
            reviews_count?: number | null;
        };
        vehicle?: {
            id: number;
            title: string;
            registration_number?: string | null;
            body_type?: string | null;
            capacity_kg?: number | null;
            volume_m3?: number | null;
            assigned_driver?: {
                name?: string | null;
                phone?: string | null;
            } | null;
            url: string;
        } | null;
    };
    latest_event?: DeliveryEvent | null;
    events: DeliveryEvent[];
    canComplete: boolean;
    canUpdateDelivery: boolean;
    deliveryEventOptions: string[];
};

type Props = {
    delivery: Delivery;
};

const statusLabels: Record<string, string> = {
    in_progress: 'В работе',
    completed: 'Завершён',
    cancelled: 'Отменён',
};

const eventLabels: Record<string, string> = {
    carrier_selected: 'Перевозчик выбран',
    en_route_to_pickup: 'Едет на погрузку',
    arrived_pickup: 'Прибыл на погрузку',
    loaded: 'Груз принят',
    in_transit: 'В пути',
    arrived_unloading: 'Прибыл на выгрузку',
    delivered_pending_confirmation: 'Ожидает подтверждения доставки',
    shipper_note: 'Комментарий заказчика',
    issue_reported: 'Проблема по перевозке',
    delivery_confirmed: 'Доставка подтверждена',
};

const statusTone: Record<string, string> = {
    in_progress: 'border-emerald-200 bg-emerald-50 text-emerald-800',
    completed: 'border-slate-200 bg-slate-50 text-slate-700',
    cancelled: 'border-rose-200 bg-rose-50 text-rose-800',
};

export default function Delivery({ delivery }: Props) {
    const load = delivery.load;
    const carrier = delivery.carrier;
    const completeForm = useForm({
        delivery_confirmation: new URLSearchParams(window.location.search).get('confirm') ?? '',
    });
    const eventForm = useForm({
        type: delivery.deliveryEventOptions[0] ?? 'shipper_note',
        note: '',
    });
    const currentStage = load.delivery_stage ? eventLabels[load.delivery_stage] ?? load.delivery_stage : 'Этап не задан';
    const price = load.price ? `${load.price.toLocaleString('ru-RU')} руб.` : 'Цена не указана';
    const dates = formatDateRange(load.loading_date, load.unloading_date, 'Даты не указаны');
    const vehicleTitle = carrier.vehicle
        ? [carrier.vehicle.title, carrier.vehicle.registration_number].filter(Boolean).join(' · ')
        : 'Транспорт не указан';

    const completeLoad: FormEventHandler = (event) => {
        event.preventDefault();
        completeForm.patch(load.urls.complete, { preserveScroll: true });
    };

    const submitEvent: FormEventHandler = (event) => {
        event.preventDefault();
        eventForm.post(load.urls.event, {
            preserveScroll: true,
            onSuccess: () => eventForm.reset('note'),
        });
    };

    return (
        <AuthenticatedLayout breadcrumbs={[
            { title: 'Мои заказы', href: route('loads.mine') },
            { title: load.title, href: load.urls.show },
            { title: 'Исполнение' },
        ]}>
            <Head title={`Исполнение: ${load.title}`} />
            <div className="mx-auto grid max-w-6xl gap-5 px-3 py-4 sm:px-4 sm:py-6">
                <div className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-start">
                    <div>
                        <div className="flex flex-wrap items-center gap-2">
                            <Badge className={statusTone[load.status] ?? undefined} variant="outline">
                                {statusLabels[load.status] ?? load.status}
                            </Badge>
                            <span className="text-sm text-emerald-700">{currentStage}</span>
                        </div>
                        <h1 className="mt-2 text-2xl font-semibold leading-tight">{load.title}</h1>
                        <p className="mt-1 text-muted-foreground">
                            {load.loading_city} - {load.unloading_city}
                        </p>
                    </div>
                    <div className="grid grid-cols-2 gap-2 sm:flex sm:flex-wrap">
                        <Button asChild variant="secondary">
                            <Link href={load.urls.show}>Карточка</Link>
                        </Button>
                        <Button asChild variant="secondary">
                            <a href={load.urls.contract}>
                                <FileText className="size-4" />
                                Договор
                            </a>
                        </Button>
                        {carrier.contact.phone && (
                            <Button asChild>
                                <a href={`tel:${carrier.contact.phone}`}>
                                    <Phone className="size-4" />
                                    Позвонить
                                </a>
                            </Button>
                        )}
                    </div>
                </div>

                {load.cargo_photo_url && (
                    <img src={load.cargo_photo_url} alt="" className="max-h-[320px] w-full rounded-md object-cover" />
                )}

                <section className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <Info label="Этап" value={currentStage} />
                    <Info label="Перевозчик" value={carrier.company.name || carrier.contact.name || 'Не указан'} />
                    <Info label="Транспорт" value={vehicleTitle} />
                    <Info label="Цена" value={price} />
                </section>

                {delivery.canComplete && (
                    <section className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(320px,420px)]">
                        <form onSubmit={completeLoad} className="grid gap-3 rounded-md border border-emerald-200 bg-emerald-50 p-4">
                            <div>
                                <h2 className="font-semibold text-emerald-950">Подтверждение доставки</h2>
                                <p className="mt-1 text-sm text-emerald-900">
                                    Введите код с телефона перевозчика или откройте QR-ссылку. После подтверждения заказ перейдёт в завершённые.
                                </p>
                            </div>
                            <div className="flex flex-col gap-2 sm:flex-row">
                                <Input
                                    value={completeForm.data.delivery_confirmation}
                                    onChange={(event) => completeForm.setData('delivery_confirmation', event.target.value)}
                                    placeholder="Код или QR-токен"
                                />
                                <Button disabled={completeForm.processing || !completeForm.data.delivery_confirmation}>
                                    <CheckCircle2 className="size-4" />
                                    Завершить
                                </Button>
                            </div>
                            {completeForm.errors.delivery_confirmation && (
                                <p className="text-sm text-destructive">{completeForm.errors.delivery_confirmation}</p>
                            )}
                        </form>

                        {delivery.canUpdateDelivery && delivery.deliveryEventOptions.length > 0 && (
                            <form onSubmit={submitEvent} className="grid gap-3 rounded-md border p-4">
                                <div>
                                    <h2 className="font-semibold">Комментарий по рейсу</h2>
                                    <p className="mt-1 text-sm text-muted-foreground">
                                        Для заказчика доступны служебные заметки и фиксация проблем.
                                    </p>
                                </div>
                                <select
                                    value={eventForm.data.type}
                                    onChange={(event) => eventForm.setData('type', event.target.value)}
                                    className="min-h-10 rounded-md border bg-background px-3 py-2 text-sm"
                                >
                                    {delivery.deliveryEventOptions.map((option) => (
                                        <option key={option} value={option}>
                                            {eventLabels[option] ?? option}
                                        </option>
                                    ))}
                                </select>
                                <Input
                                    value={eventForm.data.note}
                                    onChange={(event) => eventForm.setData('note', event.target.value)}
                                    placeholder="Комментарий к событию"
                                />
                                <Button disabled={eventForm.processing || !eventForm.data.type} variant="secondary">
                                    <MessageSquareWarning className="size-4" />
                                    Добавить
                                </Button>
                                {eventForm.errors.type && <p className="text-sm text-destructive">{eventForm.errors.type}</p>}
                                {eventForm.errors.note && <p className="text-sm text-destructive">{eventForm.errors.note}</p>}
                            </form>
                        )}
                    </section>
                )}

                {load.completion_confirmed_at && (
                    <section className="rounded-md border border-emerald-200 bg-emerald-50 p-4 text-emerald-950">
                        Доставка подтверждена: {load.completion_confirmed_at}
                    </section>
                )}

                <section className="grid gap-4 lg:grid-cols-3">
                    <Panel title="Маршрут">
                        <Line label="Погрузка" value={[load.loading_city, load.loading_region, load.loading_address].filter(Boolean).join(', ')} />
                        <Line label="Выгрузка" value={[load.unloading_city, load.unloading_region, load.unloading_address].filter(Boolean).join(', ')} />
                        <Line label="Даты" value={dates} />
                    </Panel>
                    <Panel title="Груз и оплата">
                        <Line label="Тип груза" value={load.cargo_type} />
                        <Line label="Кузов" value={load.body_type} />
                        <Line label="Вес" value={load.weight_kg ? `${load.weight_kg} кг` : null} />
                        <Line label="Объём" value={load.volume_m3 ? `${load.volume_m3} м3` : null} />
                        <Line label="Мест" value={load.places_count} />
                        <Line label="Оплата" value={load.payment_terms || price} />
                    </Panel>
                    <Panel title="Перевозчик">
                        <Line label="Компания" value={carrier.company.name} />
                        <Line label="Контакт" value={carrier.contact.name} />
                        <Line label="Телефон" value={carrier.contact.phone} />
                        <Line label="Email" value={carrier.contact.email} />
                        <Line label="Договор" value={carrier.contract_signed_at ? `подписан ${formatDateTime(carrier.contract_signed_at)}` : 'ожидает фиксации'} />
                    </Panel>
                </section>

                <section className="grid gap-4 lg:grid-cols-[1fr_1fr]">
                    <Panel title="Транспорт">
                        <div className="flex flex-wrap items-start gap-3">
                            <Truck className="mt-1 size-5 text-muted-foreground" />
                            <div>
                                {carrier.vehicle ? (
                                    <>
                                        <Link className="font-medium underline" href={carrier.vehicle.url}>{vehicleTitle}</Link>
                                        <Line label="Кузов" value={carrier.vehicle.body_type} />
                                        <Line label="Грузоподъёмность" value={carrier.vehicle.capacity_kg ? `${carrier.vehicle.capacity_kg} кг` : null} />
                                        <Line label="Объём" value={carrier.vehicle.volume_m3 ? `${carrier.vehicle.volume_m3} м3` : null} />
                                        <Line label="Водитель" value={carrier.vehicle.assigned_driver?.name} />
                                        <Line label="Телефон водителя" value={carrier.vehicle.assigned_driver?.phone} />
                                    </>
                                ) : (
                                    <p className="text-sm text-muted-foreground">Транспорт не указан.</p>
                                )}
                            </div>
                        </div>
                    </Panel>

                    <Panel title="Фото от перевозчика">
                        {carrier.carrier_cargo_photo_url ? (
                            <img src={carrier.carrier_cargo_photo_url} alt="" className="h-44 w-full rounded-md object-cover" />
                        ) : (
                            <p className="text-sm text-muted-foreground">Фото после принятия груза пока не загружено.</p>
                        )}
                    </Panel>
                </section>

                <section className="grid gap-3 rounded-md border p-4">
                    <h2 className="font-semibold">История исполнения</h2>
                    {delivery.events.length === 0 ? (
                        <p className="text-sm text-muted-foreground">Событий по заказу пока нет.</p>
                    ) : (
                        <div className="grid gap-2">
                            {delivery.events.map((event) => (
                                <div key={event.id} className="rounded-md border bg-muted/20 p-3 text-sm">
                                    <div className="flex flex-wrap items-center justify-between gap-2">
                                        <p className="font-medium">{eventLabels[event.type] ?? event.type}</p>
                                        <p className="text-xs text-muted-foreground">{formatDateTime(event.created_at)}</p>
                                    </div>
                                    <p className="text-xs text-muted-foreground">
                                        {[event.actor?.name, event.actor?.role].filter(Boolean).join(' · ') || 'автор не указан'}
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

function Panel({ title, children }: { title: string; children: ReactNode }) {
    return (
        <div className="rounded-md border p-4">
            <h2 className="font-semibold">{title}</h2>
            <div className="mt-3 grid gap-2 text-sm">{children}</div>
        </div>
    );
}

function Info({ label, value }: { label: string; value?: string | null }) {
    return (
        <div className="rounded-md border p-3">
            <p className="text-xs text-muted-foreground">{label}</p>
            <p className="font-medium">{value || 'не указано'}</p>
        </div>
    );
}

function Line({ label, value }: { label: string; value?: ReactNode }) {
    return (
        <div>
            <dt className="text-xs text-muted-foreground">{label}</dt>
            <dd>{value || 'не указано'}</dd>
        </div>
    );
}
