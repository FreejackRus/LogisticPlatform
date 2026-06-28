import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useFreightTranslation } from '@/hooks/useFreightTranslation';
import { Head, Link } from '@inertiajs/react';

type Vehicle = {
    id: number;
    title: string;
    vehicle_type?: string;
    body_type?: string;
    registration_number?: string;
    capacity_kg?: number;
    volume_m3?: number;
    length_m?: number;
    width_m?: number;
    height_m?: number;
    current_city?: string;
    current_region?: string;
    is_available: boolean;
    is_online: boolean;
    is_location_visible: boolean;
    description?: string;
    photo_url?: string | null;
    last_location_at?: string;
    company?: {
        name?: string;
        phone?: string;
        email?: string;
        verification_status?: string;
    };
    carrier?: {
        name?: string;
        phone?: string;
        email?: string;
    };
};

type Props = {
    vehicle: Vehicle;
    canSeeContacts: boolean;
};

export default function Show({ vehicle, canSeeContacts }: Props) {
    const t = useFreightTranslation();

    return (
        <AuthenticatedLayout breadcrumbs={[{ title: t('common.map'), href: route('map') }, { title: vehicle.title }]}>
            <Head title={vehicle.title} />
            <div className="mx-auto grid max-w-5xl gap-5 px-4 py-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold">{vehicle.title}</h1>
                        <p className="text-muted-foreground">
                            {vehicle.company?.name || vehicle.carrier?.name || 'Перевозчик'}
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Badge>{vehicle.is_available ? t('vehicles.available') : t('vehicles.unavailable')}</Badge>
                        <Badge variant={vehicle.is_online ? 'default' : 'secondary'}>
                            {vehicle.is_online ? t('vehicles.online') : t('vehicles.offline')}
                        </Badge>
                    </div>
                </div>
                {vehicle.photo_url && (
                    <img src={vehicle.photo_url} alt="" className="max-h-[360px] w-full rounded-md object-cover" />
                )}

                <section className="grid gap-4 md:grid-cols-3">
                    <div className="rounded-md border p-4">
                        <h2 className="font-semibold">{t('common.transport')}</h2>
                        <p>{t('vehicles.fields.vehicle_type')}: {vehicle.vehicle_type || t('common.not_specified')}</p>
                        <p>{t('vehicles.fields.body_type')}: {vehicle.body_type || t('common.not_specified')}</p>
                        <p>{t('vehicles.fields.registration_number')}: {vehicle.registration_number || t('common.not_specified')}</p>
                    </div>
                    <div className="rounded-md border p-4">
                        <h2 className="font-semibold">{t('vehicles.parameters')}</h2>
                        <p>{t('vehicles.fields.capacity_kg')}: {vehicle.capacity_kg ? `${vehicle.capacity_kg} кг` : t('common.not_specified')}</p>
                        <p>{t('vehicles.fields.volume_m3')}: {vehicle.volume_m3 ? `${vehicle.volume_m3} м3` : t('common.not_specified')}</p>
                        <p>Габариты: {[vehicle.length_m, vehicle.width_m, vehicle.height_m].filter(Boolean).join(' x ') || t('common.not_specified')}</p>
                    </div>
                    <div className="rounded-md border p-4">
                        <h2 className="font-semibold">{t('common.location')}</h2>
                        <p>{[vehicle.current_city, vehicle.current_region].filter(Boolean).join(', ') || t('common.not_specified')}</p>
                        <p>{vehicle.is_location_visible ? t('vehicles.on_map') : t('vehicles.hidden_from_map')}</p>
                    </div>
                </section>

                {vehicle.description && (
                    <section className="rounded-md border p-4">
                        <h2 className="font-semibold">{t('common.description')}</h2>
                        <p className="mt-2 text-sm">{vehicle.description}</p>
                    </section>
                )}

                <section className="rounded-md border p-4">
                    <h2 className="font-semibold">{t('vehicles.carrier_contacts')}</h2>
                    {canSeeContacts ? (
                        <div className="mt-2 grid gap-1 text-sm">
                            <p>{vehicle.company?.name || vehicle.carrier?.name}</p>
                            <p>{vehicle.company?.phone || vehicle.carrier?.phone}</p>
                            <p>{vehicle.company?.email || vehicle.carrier?.email}</p>
                        </div>
                    ) : (
                        <div className="mt-2 grid gap-3 text-sm text-muted-foreground">
                            <p>{t('vehicles.contacts_after_login')}</p>
                            <Button asChild className="w-fit">
                                <Link href={route('login')}>{t('common.login')}</Link>
                            </Button>
                        </div>
                    )}
                </section>
            </div>
        </AuthenticatedLayout>
    );
}
