import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { formatDateRange, formatDateTime } from '@/lib/utils';
import { Head, Link, router } from '@inertiajs/react';
import { FileText, PackageSearch, Route, XCircle } from 'lucide-react';

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
    can_cancel: boolean;
    load: {
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
        cargo_type?: string | null;
        company_name?: string | null;
        url: string;
        delivery_url?: string | null;
        contract_url?: string | null;
    };
    vehicle?: {
        id: number;
        title: string;
        registration_number?: string | null;
        assigned_driver?: { id: number; name: string } | null;
    } | null;
    company?: { id: number; name: string } | null;
    carrier?: { id?: number | null; name?: string | null };
};

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type Props = {
    bids: {
        data: Bid[];
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
    pending: 'Ожидают решения',
    accepted: 'Приняты',
    rejected: 'Отклонены',
    cancelled: 'Отменены',
};

const bidTone: Record<string, string> = {
    pending: 'border-blue-200 bg-blue-50 text-blue-800',
    accepted: 'border-emerald-200 bg-emerald-50 text-emerald-800',
    rejected: 'border-rose-200 bg-rose-50 text-rose-800',
    cancelled: 'border-slate-200 bg-slate-50 text-slate-700',
};

const loadTone: Record<string, string> = {
    active: 'border-blue-200 bg-blue-50 text-blue-800',
    in_progress: 'border-emerald-200 bg-emerald-50 text-emerald-800',
    completed: 'border-slate-200 bg-slate-50 text-slate-700',
    cancelled: 'border-rose-200 bg-rose-50 text-rose-800',
};

export default function Bids({ bids, currentStatus, statusCounts }: Props) {
    return (
        <AuthenticatedLayout breadcrumbs={[{ title: 'Мои отклики' }]}>
            <Head title="Мои отклики" />
            <div className="mx-auto grid max-w-6xl gap-5 px-3 py-4 sm:px-4 sm:py-6">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold">Мои отклики</h1>
                        <p className="mt-1 max-w-2xl text-sm text-muted-foreground">
                            Рабочий список всех отправленных откликов: ожидание выбора, принятые рейсы, отклонения и отмены.
                        </p>
                    </div>
                    <Button asChild variant="secondary">
                        <Link href={route('loads.index')}>
                            <PackageSearch className="size-4" />
                            Найти грузы
                        </Link>
                    </Button>
                </div>

                <div className="flex flex-wrap gap-2">
                    {Object.entries(statusLabels).map(([status, label]) => (
                        <Button key={status} asChild variant={currentStatus === status ? 'default' : 'secondary'} size="sm">
                            <Link href={route('bids.mine', status === 'all' ? {} : { status })}>
                                {label}
                                <span className="ml-2 rounded bg-background/20 px-1.5 text-xs">{statusCounts[status] ?? 0}</span>
                            </Link>
                        </Button>
                    ))}
                </div>

                <div className="text-sm text-muted-foreground">
                    {bids.total > 0
                        ? `Показаны ${bids.from ?? 0}-${bids.to ?? 0} из ${bids.total}`
                        : 'По выбранному статусу откликов пока нет.'}
                </div>

                <div className="grid gap-3">
                    {bids.data.map((bid) => <BidCard key={bid.id} bid={bid} />)}
                    {bids.data.length === 0 && (
                        <div className="rounded-md border border-dashed p-8 text-center">
                            <PackageSearch className="mx-auto size-9 text-muted-foreground" />
                            <h2 className="mt-3 font-semibold">Откликов нет</h2>
                            <p className="mt-1 text-sm text-muted-foreground">
                                Откликайтесь на подходящие грузы из каталога. После отправки они появятся здесь.
                            </p>
                            <Button asChild className="mt-4">
                                <Link href={route('loads.index')}>Открыть каталог грузов</Link>
                            </Button>
                        </div>
                    )}
                </div>

                {bids.links.length > 3 && (
                    <div className="flex flex-wrap gap-2">
                        {bids.links.map((link) => (
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

function BidCard({ bid }: { bid: Bid }) {
    const price = bid.load.price
        ? `${bid.load.price.toLocaleString('ru-RU')} ${bid.load.price_currency || 'RUB'}`
        : 'цена договорная';
    const dates = formatDateRange(bid.load.loading_date, bid.load.unloading_date);
    const vehicle = [bid.vehicle?.title, bid.vehicle?.registration_number].filter(Boolean).join(' · ') || 'транспорт не указан';
    const decisionDate = bid.accepted_at || bid.rejected_at || bid.cancelled_at || bid.created_at;

    return (
        <article className="rounded-md border bg-background p-4">
            <div className="grid gap-4 lg:grid-cols-[1fr_auto] lg:items-start">
                <div className="min-w-0">
                    <div className="flex flex-wrap items-center gap-2">
                        <Badge className={bidTone[bid.status] ?? undefined} variant="outline">
                            {statusLabels[bid.status] ?? bid.status}
                        </Badge>
                        <Badge className={loadTone[bid.load.status] ?? undefined} variant="outline">
                            Груз: {bid.load.status}
                        </Badge>
                        {bid.contract_signed_at && <Badge>Договор подписан</Badge>}
                    </div>

                    <Link href={bid.load.url} className="mt-2 block text-lg font-semibold hover:underline">
                        {bid.load.title}
                    </Link>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {bid.load.loading_city} - {bid.load.unloading_city}
                    </p>
                    <div className="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-sm">
                        <span>{price}</span>
                        <span className="text-muted-foreground">{dates}</span>
                        <span className="text-muted-foreground">{bid.load.body_type || 'кузов не указан'}</span>
                    </div>
                </div>

                <div className="grid gap-2 sm:grid-cols-2 lg:min-w-56 lg:grid-cols-1">
                    <Button asChild size="sm">
                        <Link href={bid.load.url}>Открыть груз</Link>
                    </Button>
                    {bid.load.delivery_url && (
                        <Button asChild size="sm" variant="secondary">
                            <Link href={bid.load.delivery_url}>
                                <Route className="size-4" />
                                Открыть рейс
                            </Link>
                        </Button>
                    )}
                    {bid.load.contract_url && (
                        <Button asChild size="sm" variant="secondary">
                            <Link href={bid.load.contract_url}>
                                <FileText className="size-4" />
                                Договор
                            </Link>
                        </Button>
                    )}
                    {bid.can_cancel && bid.status === 'pending' && (
                        <Button
                            type="button"
                            size="sm"
                            variant="destructive"
                            onClick={() => router.patch(route('bids.cancel', bid.id))}
                        >
                            <XCircle className="size-4" />
                            Отменить
                        </Button>
                    )}
                </div>
            </div>

            <div className="mt-4 grid gap-3 lg:grid-cols-3">
                <Info label="Транспорт" value={vehicle} />
                <Info label="Компания" value={bid.company?.name || bid.load.company_name || 'не указана'} />
                <Info label="Дата" value={formatDateTime(decisionDate)} />
            </div>

            {bid.vehicle?.assigned_driver && (
                <p className="mt-3 text-sm text-muted-foreground">
                    Водитель: {bid.vehicle.assigned_driver.name}
                </p>
            )}
            {bid.comment && (
                <p className="mt-3 rounded-md bg-muted/40 p-3 text-sm">{bid.comment}</p>
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
