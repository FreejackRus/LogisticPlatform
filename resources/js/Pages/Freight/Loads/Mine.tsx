import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { formatDateRange, formatDateTime } from '@/lib/utils';
import { Head, Link } from '@inertiajs/react';
import { FileText, Pencil, Plus, Route, Truck } from 'lucide-react';
import { ReactNode } from 'react';

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
    views_count: number;
    is_urgent: boolean;
    created_at?: string | null;
    published_at?: string | null;
    completed_at?: string | null;
    accepted_bid?: {
        id: number;
        carrier_name?: string | null;
        vehicle_title?: string | null;
        contract_signed_at?: string | null;
    } | null;
    latest_event?: {
        type: string;
        created_at?: string | null;
    } | null;
    urls: {
        show: string;
        edit: string;
        contract?: string | null;
        candidates: string;
        delivery?: string | null;
    };
};

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type Props = {
    loads: {
        data: Load[];
        from: number | null;
        to: number | null;
        total: number;
        links: PaginationLink[];
    };
    currentStatus: string;
    statusCounts: Record<string, number>;
};

const statusLabels: Record<string, string> = {
    all: 'Все',
    draft: 'Черновики',
    active: 'Опубликованы',
    in_progress: 'В перевозке',
    completed: 'Завершены',
    cancelled: 'Отменены',
    archived: 'Архив',
};

const deliveryLabels: Record<string, string> = {
    carrier_selected: 'Перевозчик выбран',
    en_route_to_pickup: 'Едет на погрузку',
    arrived_pickup: 'Прибыл на погрузку',
    loaded: 'Груз загружен',
    in_transit: 'В пути',
    arrived_unloading: 'Прибыл на выгрузку',
    delivered_pending_confirmation: 'Ожидает подтверждения доставки',
    delivery_confirmed: 'Доставка подтверждена',
    shipper_note: 'Комментарий заказчика',
    issue_reported: 'Проблема',
};

const statusTone: Record<string, string> = {
    draft: 'border-slate-200 bg-slate-50 text-slate-700',
    active: 'border-blue-200 bg-blue-50 text-blue-800',
    in_progress: 'border-emerald-200 bg-emerald-50 text-emerald-800',
    completed: 'border-slate-200 bg-slate-50 text-slate-700',
    cancelled: 'border-rose-200 bg-rose-50 text-rose-800',
    archived: 'border-slate-200 bg-slate-50 text-slate-700',
};

export default function Mine({ loads, currentStatus, statusCounts }: Props) {
    return (
        <AuthenticatedLayout breadcrumbs={[{ title: 'Мои заказы' }]}>
            <Head title="Мои заказы" />
            <div className="grid gap-5 px-4 py-6">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold">Мои заказы</h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Рабочий список грузов: публикация, отклики, выбранный перевозчик, договор и доставка.
                        </p>
                    </div>
                    <Button asChild>
                        <Link href={route('loads.create')}>
                            <Plus className="size-4" />
                            Создать груз
                        </Link>
                    </Button>
                </div>

                <div className="flex flex-wrap gap-2">
                    {Object.entries(statusLabels).map(([status, label]) => (
                        <Button key={status} asChild variant={currentStatus === status ? 'default' : 'secondary'} size="sm">
                            <Link href={route('loads.mine', status === 'all' ? {} : { status })}>
                                {label}
                                <span className="ml-2 rounded bg-background/20 px-1.5 text-xs">{statusCounts[status] ?? 0}</span>
                            </Link>
                        </Button>
                    ))}
                </div>

                <div className="text-sm text-muted-foreground">
                    {loads.total > 0
                        ? `Показаны ${loads.from ?? 0}-${loads.to ?? 0} из ${loads.total}`
                        : 'По выбранному статусу заказов пока нет.'}
                </div>

                <div className="grid gap-3">
                    {loads.data.map((load) => <LoadCard key={load.id} load={load} />)}
                    {loads.data.length === 0 && (
                        <div className="rounded-md border border-dashed p-8 text-center">
                            <h2 className="font-semibold">Заказов нет</h2>
                            <p className="mt-2 text-sm text-muted-foreground">Создайте груз или переключите фильтр статуса.</p>
                            <Button asChild className="mt-4">
                                <Link href={route('loads.create')}>Создать груз</Link>
                            </Button>
                        </div>
                    )}
                </div>

                {loads.links.length > 3 && (
                    <div className="flex flex-wrap gap-2">
                        {loads.links.map((link) => (
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

function LoadCard({ load }: { load: Load }) {
    const price = load.price
        ? `${load.price.toLocaleString('ru-RU')} ${load.price_currency || 'RUB'}`
        : 'по договоренности';

    return (
        <section className="rounded-md border p-4">
            <div className="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                <div className="min-w-0">
                    <div className="flex flex-wrap items-center gap-2">
                        <Badge className={statusTone[load.status] ?? undefined} variant="outline">
                            {statusLabels[load.status] ?? load.status}
                        </Badge>
                        {load.is_urgent && <Badge>Срочно</Badge>}
                        {load.pending_bids_count > 0 && <Badge variant="secondary">Новые отклики: {load.pending_bids_count}</Badge>}
                    </div>
                    <Link href={load.urls.show} className="mt-2 block text-lg font-semibold hover:underline">
                        {load.title}
                    </Link>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {load.loading_city} - {load.unloading_city}
                    </p>
                    <p className="mt-1 text-xs text-muted-foreground">
                        {formatDateRange(load.loading_date, load.unloading_date)}
                    </p>
                </div>
                <div className="text-sm md:text-right">
                    <div className="font-medium">{price}</div>
                    <div className="text-muted-foreground">{load.body_type || 'кузов не указан'}</div>
                    <div className="text-muted-foreground">Просмотры: {load.views_count}</div>
                </div>
            </div>

            <div className="mt-4 grid gap-3 lg:grid-cols-3">
                <NestedBlock title="Отклики">
                    <div className="text-2xl font-semibold">{load.bids_count}</div>
                    <p className="text-sm text-muted-foreground">
                        {load.pending_bids_count > 0 ? `ожидают решения: ${load.pending_bids_count}` : 'новых откликов нет'}
                    </p>
                </NestedBlock>

                <NestedBlock title="Перевозчик">
                    {load.accepted_bid ? (
                        <>
                            <p className="font-medium">{load.accepted_bid.carrier_name || 'перевозчик'}</p>
                            <p className="text-sm text-muted-foreground">{load.accepted_bid.vehicle_title || 'транспорт не указан'}</p>
                            <p className="text-xs text-emerald-700">
                                {load.accepted_bid.contract_signed_at ? `договор: ${formatDateTime(load.accepted_bid.contract_signed_at)}` : 'договор ожидает фиксации'}
                            </p>
                        </>
                    ) : (
                        <p className="text-sm text-muted-foreground">Итоговый перевозчик еще не выбран.</p>
                    )}
                </NestedBlock>

                <NestedBlock title="Доставка">
                    <p className="font-medium">
                        {load.delivery_stage ? deliveryLabels[load.delivery_stage] ?? load.delivery_stage : 'этап не задан'}
                    </p>
                    <p className="text-sm text-muted-foreground">
                        {load.latest_event ? `последнее событие: ${formatDateTime(load.latest_event.created_at)}` : 'событий пока нет'}
                    </p>
                </NestedBlock>
            </div>

            <div className="mt-4 flex flex-wrap gap-2">
                <Button asChild size="sm">
                    <Link href={load.urls.show}>Открыть заказ</Link>
                </Button>
                <Button asChild size="sm" variant={load.pending_bids_count > 0 ? 'default' : 'secondary'}>
                    <Link href={load.urls.candidates}>
                        Отклики
                        {load.pending_bids_count > 0 && <span className="ml-1">({load.pending_bids_count})</span>}
                    </Link>
                </Button>
                {['draft', 'active'].includes(load.status) && (
                    <Button asChild size="sm" variant="secondary">
                        <Link href={load.urls.edit}>
                            <Pencil className="size-4" />
                            Редактировать
                        </Link>
                    </Button>
                )}
                {load.urls.contract && (
                    <Button asChild size="sm" variant="secondary">
                        <Link href={load.urls.contract}>
                            <FileText className="size-4" />
                            Договор
                        </Link>
                    </Button>
                )}
                {load.status === 'in_progress' && (
                    <Button asChild size="sm" variant="secondary">
                        <Link href={load.urls.delivery || load.urls.show}>
                            <Route className="size-4" />
                            Доставка
                        </Link>
                    </Button>
                )}
                {load.accepted_bid && (
                    <Button asChild size="sm" variant="secondary">
                        <Link href={load.urls.show}>
                            <Truck className="size-4" />
                            Перевозчик
                        </Link>
                    </Button>
                )}
            </div>
        </section>
    );
}

function NestedBlock({ title, children }: { title: string; children: ReactNode }) {
    return (
        <div className="rounded-md border bg-muted/20 p-3">
            <h3 className="text-sm font-medium">{title}</h3>
            <div className="mt-2">{children}</div>
        </div>
    );
}
