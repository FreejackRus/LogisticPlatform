import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { formatDateRange } from '@/lib/utils';
import { Head, Link, router } from '@inertiajs/react';
import { CheckCircle2, FileText, Phone, Truck } from 'lucide-react';

type Load = {
    id: number;
    title: string;
    status: string;
    delivery_stage?: string | null;
    loading_city: string;
    unloading_city: string;
    loading_date?: string | null;
    unloading_date?: string | null;
    price?: number | null;
    price_currency?: string | null;
    body_type?: string | null;
    bids_count: number;
    pending_bids_count: number;
    accepted_bid?: {
        id: number;
        carrier_name?: string | null;
        vehicle_title?: string | null;
        contract_signed_at?: string | null;
    } | null;
    urls: {
        show: string;
        edit: string;
        contract?: string | null;
        candidates: string;
    };
};

type Bid = {
    id: number;
    status: string;
    comment?: string | null;
    created_at?: string | null;
    accepted_at?: string | null;
    rejected_at?: string | null;
    cancelled_at?: string | null;
    contract_accepted_at?: string | null;
    contract_signed_at?: string | null;
    can_accept: boolean;
    carrier_cargo_photo_url?: string | null;
    carrier?: {
        id?: number | null;
        name?: string | null;
        phone?: string | null;
        email?: string | null;
    };
    company?: {
        id?: number | null;
        name?: string | null;
        phone?: string | null;
        email?: string | null;
        verification_status?: string | null;
        rating?: string | number | null;
        reviews_count?: number | null;
    };
    vehicle?: {
        id: number;
        title: string;
        vehicle_type?: string | null;
        body_type?: string | null;
        registration_number?: string | null;
        capacity_kg?: number | null;
        volume_m3?: string | number | null;
        current_city?: string | null;
        is_online?: boolean;
        assigned_driver?: {
            id: number;
            name: string;
            phone?: string | null;
            email?: string | null;
        } | null;
        url: string;
    } | null;
    urls: {
        accept: string;
    };
};

type Props = {
    load: Load;
    bids: Bid[];
    canAcceptBids: boolean;
};

const bidLabels: Record<string, string> = {
    pending: 'Ожидает решения',
    accepted: 'Выбран',
    rejected: 'Отклонен',
    cancelled: 'Отменен',
};

const bidTone: Record<string, string> = {
    pending: 'border-blue-200 bg-blue-50 text-blue-800',
    accepted: 'border-emerald-200 bg-emerald-50 text-emerald-800',
    rejected: 'border-rose-200 bg-rose-50 text-rose-800',
    cancelled: 'border-slate-200 bg-slate-50 text-slate-700',
};

const verificationLabels: Record<string, string> = {
    verified: 'Проверена',
    pending: 'На проверке',
    rejected: 'Отклонена',
    not_verified: 'Не проверена',
};

export default function Bids({ load, bids, canAcceptBids }: Props) {
    const price = load.price
        ? `${load.price.toLocaleString('ru-RU')} ${load.price_currency || 'RUB'}`
        : 'цена договорная';
    const dates = formatDateRange(load.loading_date, load.unloading_date);

    return (
        <AuthenticatedLayout breadcrumbs={[
            { title: 'Мои заказы', href: route('loads.mine') },
            { title: load.title, href: load.urls.show },
            { title: 'Отклики' },
        ]}>
            <Head title={`Отклики: ${load.title}`} />
            <div className="mx-auto grid max-w-6xl gap-5 px-3 py-4 sm:px-4 sm:py-6">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold">Отклики по заказу</h1>
                        <p className="mt-1 max-w-2xl text-sm text-muted-foreground">
                            {load.title} · {load.loading_city} - {load.unloading_city}
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button asChild variant="secondary">
                            <Link href={load.urls.show}>Открыть груз</Link>
                        </Button>
                        {load.urls.contract && (
                            <Button asChild variant="secondary">
                                <Link href={load.urls.contract}>
                                    <FileText className="size-4" />
                                    Договор
                                </Link>
                            </Button>
                        )}
                    </div>
                </div>

                <section className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <Info label="Цена" value={price} />
                    <Info label="Даты" value={dates} />
                    <Info label="Кузов" value={load.body_type || 'не указан'} />
                    <Info label="Отклики" value={`${load.bids_count} всего, ${load.pending_bids_count} новых`} />
                </section>

                {load.accepted_bid && (
                    <div className="rounded-md border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-950">
                        Итоговый перевозчик выбран: <span className="font-semibold">{load.accepted_bid.carrier_name || 'перевозчик'}</span>
                        {load.accepted_bid.vehicle_title && <> · {load.accepted_bid.vehicle_title}</>}
                    </div>
                )}

                {!canAcceptBids && !load.accepted_bid && (
                    <div className="rounded-md border border-amber-200 bg-amber-50 p-4 text-sm text-amber-950">
                        Выбор перевозчика доступен только для активного опубликованного груза.
                    </div>
                )}

                <div className="grid gap-3">
                    {bids.map((bid) => <BidCard key={bid.id} bid={bid} canAcceptBids={canAcceptBids} />)}
                    {bids.length === 0 && (
                        <div className="rounded-md border border-dashed p-8 text-center">
                            <Truck className="mx-auto size-9 text-muted-foreground" />
                            <h2 className="mt-3 font-semibold">Откликов пока нет</h2>
                            <p className="mt-1 text-sm text-muted-foreground">
                                Когда перевозчики откликнутся на заказ, они появятся здесь для сравнения.
                            </p>
                        </div>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

function BidCard({ bid, canAcceptBids }: { bid: Bid; canAcceptBids: boolean }) {
    const companyName = bid.company?.name || bid.carrier?.name || 'Перевозчик';
    const vehicleLine = bid.vehicle
        ? [bid.vehicle.title, bid.vehicle.registration_number, bid.vehicle.body_type].filter(Boolean).join(' · ')
        : 'транспорт не указан';
    const capacity = [
        bid.vehicle?.capacity_kg ? `${bid.vehicle.capacity_kg.toLocaleString('ru-RU')} кг` : null,
        bid.vehicle?.volume_m3 ? `${bid.vehicle.volume_m3} м3` : null,
    ].filter(Boolean).join(' · ') || 'параметры не указаны';

    return (
        <article className="rounded-md border bg-background p-4">
            <div className="grid gap-4 lg:grid-cols-[1fr_auto] lg:items-start">
                <div>
                    <div className="flex flex-wrap items-center gap-2">
                        <Badge className={bidTone[bid.status] ?? undefined} variant="outline">
                            {bidLabels[bid.status] ?? bid.status}
                        </Badge>
                        {bid.company?.verification_status && (
                            <Badge variant="secondary">
                                {verificationLabels[bid.company.verification_status] ?? bid.company.verification_status}
                            </Badge>
                        )}
                        {bid.contract_accepted_at && <Badge>Договор принят</Badge>}
                    </div>
                    <h2 className="mt-2 text-lg font-semibold">{companyName}</h2>
                    <p className="text-sm text-muted-foreground">{bid.carrier?.name || 'контактное лицо не указано'}</p>
                </div>

                <div className="grid gap-2 sm:grid-cols-2 lg:min-w-56 lg:grid-cols-1">
                    {bid.can_accept && canAcceptBids && (
                        <Button type="button" onClick={() => router.patch(bid.urls.accept)}>
                            <CheckCircle2 className="size-4" />
                            Выбрать перевозчика
                        </Button>
                    )}
                    {bid.company?.phone && (
                        <Button asChild variant="secondary">
                            <a href={`tel:${bid.company.phone}`}>
                                <Phone className="size-4" />
                                Позвонить
                            </a>
                        </Button>
                    )}
                    {bid.vehicle?.url && (
                        <Button asChild variant="secondary">
                            <Link href={bid.vehicle.url}>Открыть машину</Link>
                        </Button>
                    )}
                </div>
            </div>

            <div className="mt-4 grid gap-3 lg:grid-cols-3">
                <Info label="Транспорт" value={vehicleLine} />
                <Info label="Грузоподъемность" value={capacity} />
                <Info label="Город" value={bid.vehicle?.current_city || 'не указан'} />
            </div>

            {bid.vehicle?.assigned_driver && (
                <p className="mt-3 text-sm text-muted-foreground">
                    Водитель: {bid.vehicle.assigned_driver.name}
                    {bid.vehicle.assigned_driver.phone && <> · {bid.vehicle.assigned_driver.phone}</>}
                </p>
            )}

            {bid.comment && <p className="mt-3 rounded-md bg-muted/40 p-3 text-sm">{bid.comment}</p>}

            {bid.carrier_cargo_photo_url && (
                <img src={bid.carrier_cargo_photo_url} alt="" className="mt-3 h-32 w-52 rounded-md object-cover" />
            )}
        </article>
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
