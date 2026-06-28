import { Button } from '@/Components/ui/button';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

type Props = {
    stats: Record<string, number>;
    loads: Array<{ id: number; title: string; loading_city: string; unloading_city: string }>;
    vehicles: Array<{ id: number; title: string; current_city?: string; is_online: boolean; body_type?: string }>;
    connections: Array<{ id: number; status: string; freight_load?: { title: string } }>;
};

export default function Index({ stats, loads, vehicles, connections }: Props) {
    const labels: Record<string, string> = {
        loadsWithoutBids: 'Грузы без откликов',
        urgentLoads: 'Срочные грузы',
        newLoads24h: 'Новые за 24 часа',
        onlineVehicles: 'Онлайн-транспорт',
        openConnections: 'Открытые соединения',
        openComplaints: 'Жалобы в работе',
    };
    const connectionStatusLabels: Record<string, string> = {
        draft: 'Черновик',
        proposed: 'Предложено',
        contacted: 'Стороны уведомлены',
        connected: 'Стороны связаны',
        declined: 'Отказ',
        no_answer: 'Нет ответа',
        cancelled: 'Отменено',
        closed: 'Закрыто',
    };

    return (
        <AuthenticatedLayout breadcrumbs={[{ title: 'Диспетчер' }]}>
            <Head title="Диспетчер" />
            <div className="grid gap-6 px-4 py-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <h1 className="text-2xl font-semibold">Диспетчерский дашборд</h1>
                    <div className="flex gap-2">
                        <Button asChild variant="secondary"><Link href={route('dispatcher.map')}>Карта</Link></Button>
                        <Button asChild><Link href={route('dispatcher.connections.index')}>Соединения</Link></Button>
                    </div>
                </div>
                <section className="grid gap-3 md:grid-cols-3">
                    {Object.entries(stats).map(([key, value]) => (
                        <div key={key} className="rounded-md border p-4">
                            <p className="text-sm text-muted-foreground">{labels[key]}</p>
                            <p className="text-3xl font-semibold">{value}</p>
                        </div>
                    ))}
                </section>
                <section className="grid gap-4 lg:grid-cols-3">
                    <div className="rounded-md border p-4">
                        <h2 className="font-semibold">Активные грузы</h2>
                        <div className="mt-3 grid gap-2">
                            {loads.length === 0 && <p className="text-sm text-muted-foreground">Нет активных грузов.</p>}
                            {loads.map((load) => (
                                <Link key={load.id} href={route('loads.show', load.id)} className="rounded-md border px-3 py-2 text-sm hover:bg-muted">
                                    <span className="block font-medium">{load.title}</span>
                                    <span className="text-muted-foreground">{load.loading_city} - {load.unloading_city}</span>
                                </Link>
                            ))}
                        </div>
                    </div>
                    <div className="rounded-md border p-4">
                        <h2 className="font-semibold">Онлайн-транспорт</h2>
                        <div className="mt-3 grid gap-2">
                            {vehicles.length === 0 && <p className="text-sm text-muted-foreground">Нет транспорта онлайн.</p>}
                            {vehicles.map((vehicle) => (
                                <Link key={vehicle.id} href={route('vehicles.show', vehicle.id)} className="rounded-md border px-3 py-2 text-sm hover:bg-muted">
                                    <span className="block font-medium">{vehicle.title}</span>
                                    <span className="text-muted-foreground">
                                        {[vehicle.body_type, vehicle.current_city || 'без города'].filter(Boolean).join(' · ')}
                                    </span>
                                </Link>
                            ))}
                        </div>
                    </div>
                    <div className="rounded-md border p-4">
                        <h2 className="font-semibold">Последние соединения</h2>
                        <div className="mt-3 grid gap-2">
                            {connections.length === 0 && <p className="text-sm text-muted-foreground">Соединений пока нет.</p>}
                            {connections.map((connection) => (
                                <Link key={connection.id} href={route('dispatcher.connections.show', connection.id)} className="rounded-md border px-3 py-2 text-sm hover:bg-muted">
                                    <span className="block font-medium">#{connection.id} · {connection.freight_load?.title || 'груз не указан'}</span>
                                    <span className="text-muted-foreground">{connectionStatusLabels[connection.status] ?? connection.status}</span>
                                </Link>
                            ))}
                        </div>
                    </div>
                </section>
            </div>
        </AuthenticatedLayout>
    );
}
