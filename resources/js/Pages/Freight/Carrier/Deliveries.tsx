import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { useFreightTranslation } from '@/hooks/useFreightTranslation';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { formatDateRange } from '@/lib/utils';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { MapPinned, PackageOpen } from 'lucide-react';

type Delivery = {
    bid_id: number;
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
        contact_phone?: string | null;
        cargo_photo_url?: string | null;
        carrier_delivery_url: string;
        contract_url: string;
        route_url: string;
        next_delivery_event?: string | null;
    };
    vehicle?: {
        id: number;
        title: string;
        registration_number?: string | null;
    } | null;
    latest_event?: {
        type: string;
        note?: string | null;
        created_at: string;
    } | null;
    carrier_cargo_photo_url?: string | null;
};

type Props = {
    deliveries: Delivery[];
    filters: { status: string };
    stats: { active: number; completed: number };
};

const statusTone: Record<string, string> = {
    in_progress: 'border-emerald-200 bg-emerald-50 text-emerald-800',
    completed: 'border-slate-200 bg-slate-50 text-slate-700',
};

export default function Deliveries({ deliveries, filters, stats }: Props) {
    const t = useFreightTranslation();
    const { auth } = usePage().props as any;
    const canFindLoads = !auth?.user?.is_carrier_company_driver;

    const setStatus = (status: string) => {
        router.get(route('carrier.deliveries.index'), { status }, { preserveScroll: true });
    };

    return (
        <AuthenticatedLayout breadcrumbs={[{ title: t('carrier_deliveries.title') }]}>
            <Head title={t('carrier_deliveries.title')} />
            <div className="mx-auto grid max-w-6xl gap-5 px-3 py-4 sm:px-4 sm:py-6">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold">{t('carrier_deliveries.title')}</h1>
                        <p className="mt-1 max-w-2xl text-sm text-muted-foreground">
                            {t('carrier_deliveries.list_subtitle')}
                        </p>
                    </div>
                    <div className="grid w-full grid-cols-3 rounded-md border p-1 sm:w-auto">
                        {[
                            ['active', t('carrier_deliveries.tabs.active', { count: stats.active })],
                            ['completed', t('carrier_deliveries.tabs.completed', { count: stats.completed })],
                            ['all', t('carrier_deliveries.tabs.all')],
                        ].map(([value, label]) => (
                            <button
                                key={value}
                                type="button"
                                onClick={() => setStatus(value)}
                                className={`min-h-10 rounded px-2 text-sm ${filters.status === value ? 'bg-primary text-primary-foreground' : 'text-muted-foreground'}`}
                            >
                                {label}
                            </button>
                        ))}
                    </div>
                </div>

                {deliveries.length === 0 ? (
                    <div className="rounded-md border border-dashed p-8 text-center">
                        <PackageOpen className="mx-auto size-9 text-muted-foreground" />
                        <h2 className="mt-3 font-semibold">{t('carrier_deliveries.empty_title')}</h2>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {t('carrier_deliveries.empty_text')}
                        </p>
                        {canFindLoads && (
                            <Button asChild className="mt-4" variant="secondary">
                                <Link href={route('loads.index')}>{t('carrier_deliveries.find_loads')}</Link>
                            </Button>
                        )}
                    </div>
                ) : (
                    <div className="grid gap-3">
                        {deliveries.map((delivery) => (
                            <DeliveryRow key={delivery.bid_id} delivery={delivery} />
                        ))}
                    </div>
                )}
            </div>
        </AuthenticatedLayout>
    );
}

function DeliveryRow({ delivery }: { delivery: Delivery }) {
    const t = useFreightTranslation();
    const currentStage = delivery.load.delivery_stage
        ? t(`delivery_events.${delivery.load.delivery_stage}`)
        : t('carrier_deliveries.stage_not_set');
    const dates = formatDateRange(delivery.load.loading_date, delivery.load.unloading_date, t('common.not_specified'));
    const price = delivery.load.price
        ? t('loads.price_rub', { price: delivery.load.price.toLocaleString('ru-RU') })
        : t('loads.negotiable_price');
    const needsCarrierPhoto = delivery.load.next_delivery_event === 'loaded' && !delivery.carrier_cargo_photo_url;
    const vehicle = [delivery.vehicle?.title, delivery.vehicle?.registration_number]
        .filter(Boolean)
        .join(' · ') || t('common.not_specified');

    return (
        <article className="grid gap-3 rounded-md border bg-background p-3 sm:grid-cols-[96px_1fr_auto] sm:items-start sm:p-4">
            {delivery.load.cargo_photo_url ? (
                <img src={delivery.load.cargo_photo_url} alt="" className="h-24 w-full rounded-md object-cover" />
            ) : (
                <div className="flex h-24 w-full items-center justify-center rounded-md bg-muted text-sm text-muted-foreground">
                    {t('carrier_deliveries.no_cargo_photo')}
                </div>
            )}

            <div className="min-w-0">
                <div className="flex flex-wrap items-center gap-2">
                    <Badge className={statusTone[delivery.load.status] ?? undefined} variant="outline">
                        {t(`carrier_deliveries.statuses.${delivery.load.status}`)}
                    </Badge>
                    <span className="text-xs text-muted-foreground">{currentStage}</span>
                </div>
                <h2 className="mt-1 text-lg font-semibold leading-tight">
                    <Link className="hover:underline" href={delivery.load.carrier_delivery_url}>
                        {delivery.load.title}
                    </Link>
                </h2>
                <p className="mt-1 text-sm text-muted-foreground">
                    {delivery.load.loading_city} - {delivery.load.unloading_city}
                </p>
                <div className="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-sm">
                    <span>{price}</span>
                    <span className="text-muted-foreground">{dates}</span>
                    <span className="text-muted-foreground">{vehicle}</span>
                </div>
                {delivery.load.next_delivery_event && (
                    <p className="mt-2 text-sm text-muted-foreground">
                        Следующий этап: {t(`delivery_events.${delivery.load.next_delivery_event}`)}
                    </p>
                )}
                {needsCarrierPhoto && (
                    <p className="mt-1 text-sm text-amber-700">{t('carrier_deliveries.photo_required_before_loaded')}</p>
                )}
            </div>

            <div className="grid gap-2 sm:flex sm:flex-col">
                <Button asChild>
                    <Link href={delivery.load.carrier_delivery_url}>{t('carrier_deliveries.open_delivery')}</Link>
                </Button>
                {delivery.load.status === 'in_progress' && (
                    <Button asChild variant="secondary">
                        <Link href={delivery.load.route_url}>
                            <MapPinned className="size-4" />
                            {t('loads.build_route')}
                        </Link>
                    </Button>
                )}
            </div>
        </article>
    );
}
