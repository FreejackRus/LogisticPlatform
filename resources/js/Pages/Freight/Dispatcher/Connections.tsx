import { Button } from '@/Components/ui/button';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

type Connection = {
    id: number;
    status: string;
    contact_method: string;
    freight_load?: { title: string };
    dispatcher?: { name: string };
    carrier?: { name: string };
    created_at: string;
};

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type PaginatedConnections = {
    data: Connection[];
    from: number | null;
    to: number | null;
    total: number;
    links: PaginationLink[];
};

const statusLabels: Record<string, string> = {
    draft: 'Черновик',
    proposed: 'Предложено',
    contacted: 'Стороны уведомлены',
    connected: 'Стороны связаны',
    declined: 'Отказ',
    no_answer: 'Нет ответа',
    cancelled: 'Отменено',
    closed: 'Закрыто',
};

export default function Connections({ connections }: { connections: PaginatedConnections }) {
    return (
        <AuthenticatedLayout breadcrumbs={[{ title: 'Ручные соединения' }]}>
            <Head title="Ручные соединения" />
            <div className="grid gap-5 px-4 py-6">
                <div>
                    <h1 className="text-2xl font-semibold">Ручные соединения</h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {connections.total > 0
                            ? `Показаны ${connections.from ?? 0}-${connections.to ?? 0} из ${connections.total}`
                            : 'Диспетчерских соединений пока нет.'}
                    </p>
                </div>
                <div className="grid gap-3">
                    {connections.data.map((connection) => (
                        <div key={connection.id} className="flex flex-wrap items-center justify-between gap-3 rounded-md border p-4">
                            <div>
                                <p className="font-medium">#{connection.id} · {connection.freight_load?.title || 'груз не указан'}</p>
                                <p className="text-sm text-muted-foreground">
                                    {[connection.dispatcher?.name, connection.carrier?.name, statusLabels[connection.status] ?? connection.status]
                                        .filter(Boolean)
                                        .join(' · ')}
                                </p>
                            </div>
                            <Button asChild variant="secondary"><Link href={route('dispatcher.connections.show', connection.id)}>Открыть</Link></Button>
                        </div>
                    ))}
                    {connections.data.length === 0 && (
                        <div className="rounded-md border border-dashed p-8 text-center text-sm text-muted-foreground">
                            Когда диспетчер подберет перевозчика к грузу, запись появится здесь.
                        </div>
                    )}
                </div>
                {connections.links.length > 3 && (
                    <div className="flex flex-wrap gap-2">
                        {connections.links.map((link) => (
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
