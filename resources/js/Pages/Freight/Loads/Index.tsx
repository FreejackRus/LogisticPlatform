import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { useFreightTranslation } from '@/hooks/useFreightTranslation';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

type Load = {
    id: number;
    title: string;
    cargo_type?: string;
    loading_city: string;
    unloading_city: string;
    loading_date?: string;
    body_type?: string;
    weight_kg?: number;
    volume_m3?: number;
    price?: number;
    payment_type?: string;
    bids_count: number;
    views_count: number;
    is_urgent: boolean;
    can_see_contacts: boolean;
    company?: { name: string; phone?: string };
};

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type PaginatedLoads = {
    data: Load[];
    from: number | null;
    to: number | null;
    total: number;
    links: PaginationLink[];
};

type Filters = {
    q?: string;
    from_city?: string;
    to_city?: string;
    body_type?: string;
    payment_type?: string;
    min_price?: string | number;
    max_price?: string | number;
    urgent?: string | boolean;
    sort?: string;
};

type Props = {
    loads: PaginatedLoads;
    filters: Filters;
    filterOptions: { bodyTypes: string[] };
    stats: { total: number; urgent: number };
    canCreateLoad: boolean;
};

function cleanParams(filters: Filters) {
    return Object.fromEntries(
        Object.entries(filters).filter(([, value]) => value !== '' && value !== undefined && value !== false),
    );
}

export default function Index({ loads, filters, filterOptions, stats, canCreateLoad }: Props) {
    const t = useFreightTranslation();
    const [form, setForm] = useState<Filters>({
        q: filters.q ?? '',
        from_city: filters.from_city ?? '',
        to_city: filters.to_city ?? '',
        body_type: filters.body_type ?? '',
        payment_type: filters.payment_type ?? '',
        min_price: filters.min_price ?? '',
        max_price: filters.max_price ?? '',
        urgent: Boolean(filters.urgent),
        sort: filters.sort ?? 'newest',
    });

    const setField = (field: keyof Filters, value: string | boolean) => {
        setForm((current) => ({ ...current, [field]: value }));
    };

    const submit: FormEventHandler = (event) => {
        event.preventDefault();
        router.get(route('loads.index'), cleanParams(form), {
            preserveScroll: true,
            preserveState: true,
        });
    };

    const reset = () => {
        router.get(route('loads.index'), {}, { preserveScroll: true });
    };

    return (
        <AuthenticatedLayout breadcrumbs={[{ title: t('common.loads') }]}>
            <Head title={t('common.loads')} />
            <div className="px-4 py-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold">{t('loads.index_title')}</h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {t('loads.index_subtitle', {
                                total: t('loads.load_count', { count: stats.total }),
                                urgent: t('loads.urgent_count', { count: stats.urgent }),
                            })}
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button asChild variant="secondary">
                            <Link href={route('map')}>{t('common.map')}</Link>
                        </Button>
                        {canCreateLoad && (
                            <Button asChild>
                                <Link href={route('loads.create')}>{t('loads.create_button')}</Link>
                            </Button>
                        )}
                    </div>
                </div>

                <form onSubmit={submit} className="mt-5 grid gap-3 rounded-md border p-4 md:grid-cols-12">
                    <Input
                        className="md:col-span-4"
                        value={String(form.q ?? '')}
                        onChange={(event) => setField('q', event.target.value)}
                        placeholder={t('loads.filters.search_placeholder')}
                    />
                    <Input
                        className="md:col-span-2"
                        value={String(form.from_city ?? '')}
                        onChange={(event) => setField('from_city', event.target.value)}
                        placeholder={t('loads.filters.from_city')}
                    />
                    <Input
                        className="md:col-span-2"
                        value={String(form.to_city ?? '')}
                        onChange={(event) => setField('to_city', event.target.value)}
                        placeholder={t('loads.filters.to_city')}
                    />
                    <select
                        className="h-10 rounded-md border border-input bg-background px-3 text-sm md:col-span-2"
                        value={String(form.body_type ?? '')}
                        onChange={(event) => setField('body_type', event.target.value)}
                    >
                        <option value="">{t('loads.filters.any_body')}</option>
                        {filterOptions.bodyTypes.map((bodyType) => (
                            <option key={bodyType} value={bodyType}>
                                {bodyType}
                            </option>
                        ))}
                    </select>
                    <select
                        className="h-10 rounded-md border border-input bg-background px-3 text-sm md:col-span-2"
                        value={String(form.payment_type ?? '')}
                        onChange={(event) => setField('payment_type', event.target.value)}
                    >
                        <option value="">{t('loads.filters.any_payment')}</option>
                        {['bank_transfer', 'cash', 'card', 'negotiable'].map((paymentType) => (
                            <option key={paymentType} value={paymentType}>
                                {t(`loads.payment_types.${paymentType}`)}
                            </option>
                        ))}
                    </select>
                    <Input
                        className="md:col-span-2"
                        inputMode="numeric"
                        value={String(form.min_price ?? '')}
                        onChange={(event) => setField('min_price', event.target.value)}
                        placeholder={t('loads.filters.min_price')}
                    />
                    <Input
                        className="md:col-span-2"
                        inputMode="numeric"
                        value={String(form.max_price ?? '')}
                        onChange={(event) => setField('max_price', event.target.value)}
                        placeholder={t('loads.filters.max_price')}
                    />
                    <select
                        className="h-10 rounded-md border border-input bg-background px-3 text-sm md:col-span-3"
                        value={String(form.sort ?? 'newest')}
                        onChange={(event) => setField('sort', event.target.value)}
                    >
                        <option value="newest">{t('loads.sort.newest')}</option>
                        <option value="price_asc">{t('loads.sort.price_asc')}</option>
                        <option value="price_desc">{t('loads.sort.price_desc')}</option>
                    </select>
                    <label className="flex h-10 items-center gap-2 rounded-md border px-3 text-sm md:col-span-2">
                        <input
                            type="checkbox"
                            checked={Boolean(form.urgent)}
                            onChange={(event) => setField('urgent', event.target.checked)}
                        />
                        {t('loads.filters.urgent')}
                    </label>
                    <div className="flex gap-2 md:col-span-3">
                        <Button type="submit" className="flex-1">{t('loads.filters.search')}</Button>
                        <Button type="button" variant="secondary" onClick={reset}>{t('loads.filters.reset')}</Button>
                    </div>
                </form>

                <div className="mt-4 text-sm text-muted-foreground">
                    {loads.total > 0
                        ? t('loads.results', { from: loads.from ?? 0, to: loads.to ?? 0, total: loads.total })
                        : t('loads.empty_title')}
                </div>

                <div className="mt-4 grid gap-3">
                    {loads.data.map((load) => (
                        <Link key={load.id} href={route('loads.show', load.id)} className="rounded-md border p-4 hover:bg-muted">
                            <div className="flex flex-wrap items-start justify-between gap-3">
                                <div className="min-w-0">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <h2 className="font-semibold">{load.title}</h2>
                                        {load.is_urgent && <Badge>{t('loads.fields.urgent')}</Badge>}
                                        {load.cargo_type && <Badge variant="secondary">{load.cargo_type}</Badge>}
                                    </div>
                                    <p className="mt-1 text-sm text-muted-foreground">
                                        {load.loading_city} - {load.unloading_city}
                                    </p>
                                    <p className="mt-1 text-sm">
                                        {t('common.body_type')}: {load.body_type || t('loads.filters.any_body')}
                                    </p>
                                    <p className="mt-1 text-xs text-muted-foreground">
                                        {[
                                            load.weight_kg ? t('loads.weight_short', { weight: load.weight_kg.toLocaleString('ru-RU') }) : null,
                                            load.volume_m3 ? t('loads.volume_short', { volume: load.volume_m3 }) : null,
                                            t('loads.response_count', { count: load.bids_count ?? 0 }),
                                            t('loads.view_count', { count: load.views_count ?? 0 }),
                                        ].filter(Boolean).join(' · ')}
                                    </p>
                                </div>
                                <div className="text-left text-sm md:text-right">
                                    <p className="font-medium">
                                        {load.price
                                            ? t('loads.price_rub', { price: load.price.toLocaleString('ru-RU') })
                                            : t('loads.negotiable_price')}
                                    </p>
                                    {load.payment_type && (
                                        <p className="text-muted-foreground">{t(`loads.payment_types.${load.payment_type}`)}</p>
                                    )}
                                    <p className="text-muted-foreground">{load.company?.name}</p>
                                    {load.can_see_contacts && load.company?.phone && <p>{load.company.phone}</p>}
                                </div>
                            </div>
                        </Link>
                    ))}

                    {loads.data.length === 0 && (
                        <div className="rounded-md border border-dashed p-8 text-center">
                            <h2 className="font-semibold">{t('loads.empty_title')}</h2>
                            <p className="mt-2 text-sm text-muted-foreground">{t('loads.empty_text')}</p>
                            <Button type="button" variant="secondary" className="mt-4" onClick={reset}>
                                {t('loads.filters.reset')}
                            </Button>
                        </div>
                    )}
                </div>

                {loads.links.length > 3 && (
                    <div className="mt-6 flex flex-wrap gap-2">
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
