import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { formatDateTime } from '@/lib/utils';
import { Head, Link, router } from '@inertiajs/react';
import { Bell, CheckCheck, ExternalLink, Inbox } from 'lucide-react';

type NotificationItem = {
    id: number;
    type: string;
    title: string;
    message: string;
    is_read: boolean;
    created_at?: string | null;
    read_at?: string | null;
    action_url?: string | null;
};

type Props = {
    notifications: {
        data: NotificationItem[];
    };
    filters: {
        status: string;
        type?: string | null;
    };
    stats: {
        all: number;
        unread: number;
        read: number;
    };
    types: string[];
};

const typeLabels: Record<string, string> = {
    bid_created: 'Новый отклик',
    bid_accepted: 'Отклик принят',
    bid_rejected: 'Отклик отклонён',
    bid_cancelled: 'Отклик отменён',
    delivery_event: 'Этап доставки',
    load_cancelled: 'Груз отменён',
    load_completed: 'Доставка завершена',
    policy_check: 'Системное',
};

export default function Notifications({ notifications, filters, stats, types }: Props) {
    const setFilter = (next: Partial<Props['filters']>) => {
        router.get(route('notifications.index'), { ...filters, ...next }, { preserveScroll: true });
    };

    return (
        <AuthenticatedLayout breadcrumbs={[{ title: 'Уведомления' }]}>
            <Head title="Уведомления" />
            <div className="mx-auto grid max-w-5xl gap-5 px-3 py-4 sm:px-4 sm:py-6">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <div className="flex items-center gap-2 text-sm text-muted-foreground">
                            <Bell className="size-4" />
                            Рабочие события платформы
                        </div>
                        <h1 className="mt-2 text-2xl font-semibold">Уведомления</h1>
                        <p className="mt-1 max-w-2xl text-sm text-muted-foreground">
                            Отклики, выбор перевозчика, этапы рейса, отмены и завершение доставки.
                        </p>
                    </div>
                    {stats.unread > 0 && (
                        <Button type="button" variant="secondary" onClick={() => router.patch(route('notifications.read-all'))}>
                            <CheckCheck className="size-4" />
                            Прочитать всё
                        </Button>
                    )}
                </div>

                <section className="grid gap-3 md:grid-cols-[1fr_auto] md:items-center">
                    <div className="grid grid-cols-3 rounded-md border p-1">
                        {[
                            ['unread', `Новые ${stats.unread}`],
                            ['all', `Все ${stats.all}`],
                            ['read', `Прочитанные ${stats.read}`],
                        ].map(([status, label]) => (
                            <button
                                key={status}
                                type="button"
                                onClick={() => setFilter({ status })}
                                className={`min-h-10 rounded px-2 text-sm ${filters.status === status ? 'bg-primary text-primary-foreground' : 'text-muted-foreground'}`}
                            >
                                {label}
                            </button>
                        ))}
                    </div>
                    <select
                        value={filters.type ?? ''}
                        onChange={(event) => setFilter({ type: event.target.value || undefined })}
                        className="min-h-10 rounded-md border bg-background px-3 text-sm"
                    >
                        <option value="">Все типы событий</option>
                        {types.map((type) => (
                            <option key={type} value={type}>{typeLabels[type] ?? type}</option>
                        ))}
                    </select>
                </section>

                {notifications.data.length === 0 ? (
                    <div className="rounded-md border border-dashed p-8 text-center">
                        <Inbox className="mx-auto size-9 text-muted-foreground" />
                        <h2 className="mt-3 font-semibold">Уведомлений нет</h2>
                        <p className="mt-1 text-sm text-muted-foreground">Новые события по вашим грузам и рейсам появятся здесь.</p>
                    </div>
                ) : (
                    <div className="grid gap-3">
                        {notifications.data.map((item) => (
                            <article key={item.id} className={`rounded-md border p-4 ${item.is_read ? 'bg-background' : 'border-emerald-200 bg-emerald-50'}`}>
                                <div className="grid gap-3 md:grid-cols-[1fr_auto] md:items-start">
                                    <div>
                                        <div className="flex flex-wrap items-center gap-2">
                                            <Badge variant={item.is_read ? 'secondary' : 'default'}>
                                                {typeLabels[item.type] ?? item.type}
                                            </Badge>
                                            <span className="text-xs text-muted-foreground">{formatDateTime(item.created_at)}</span>
                                        </div>
                                        <h2 className="mt-2 font-semibold">{item.title}</h2>
                                        <p className="mt-1 text-sm text-muted-foreground">{item.message}</p>
                                    </div>
                                    <div className="flex flex-wrap gap-2 md:justify-end">
                                        {item.action_url && (
                                            <Button asChild size="sm" variant="secondary">
                                                <Link href={item.action_url}>
                                                    <ExternalLink className="size-4" />
                                                    Открыть
                                                </Link>
                                            </Button>
                                        )}
                                        {!item.is_read && (
                                            <Button size="sm" onClick={() => router.patch(route('notifications.read', item.id))}>
                                                Прочитано
                                            </Button>
                                        )}
                                    </div>
                                </div>
                            </article>
                        ))}
                    </div>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
