import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { ReactNode, useState } from 'react';

type AuditLog = {
    id: number;
    action: string;
    old_values_json?: Record<string, unknown> | null;
    new_values_json?: Record<string, unknown> | null;
    created_at: string;
    actor?: {
        name?: string;
        email?: string;
    } | null;
};

type Props = {
    connection: any;
    auditLogs: AuditLog[];
    disclaimer: string;
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

const contactMethodLabels: Record<string, string> = {
    phone: 'Телефон',
    email: 'Email',
    messenger: 'Мессенджер',
    platform_notification: 'Уведомление в платформе',
    other: 'Другое',
};

const fieldLabels: Record<string, string> = {
    status: 'Статус',
    internal_comment: 'Внутренний комментарий',
};

const bidStatusLabels: Record<string, string> = {
    pending: 'Ожидает решения заказчика',
    accepted: 'Выбран',
    rejected: 'Отклонён',
    cancelled: 'Отменён',
};

export default function ConnectionShow({ connection, auditLogs, disclaimer }: Props) {
    const [status, setStatus] = useState(connection.status);
    const [internalComment, setInternalComment] = useState(connection.internal_comment ?? '');

    const update = () => {
        router.patch(route('dispatcher.connections.update', connection.id), {
            status,
            internal_comment: internalComment,
        }, { preserveScroll: true });
    };

    return (
        <AuthenticatedLayout breadcrumbs={[{ title: `Соединение #${connection.id}` }]}>
            <Head title={`Соединение #${connection.id}`} />
            <div className="mx-auto grid max-w-6xl gap-6 px-4 py-6">
                <div className="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold">Соединение #{connection.id}</h1>
                        <p className="text-sm text-muted-foreground">
                            Ручная связка груза, перевозчика и машины через диспетчера платформы.
                        </p>
                    </div>
                    <Badge className="w-fit">{statusLabels[connection.status] ?? connection.status}</Badge>
                </div>

                <div className="grid gap-4 md:grid-cols-3">
                    <InfoSection title="Груз">
                        <Line label="Название" value={connection.freight_load?.title} />
                        <Line label="Маршрут" value={`${connection.freight_load?.loading_city ?? '-'} - ${connection.freight_load?.unloading_city ?? '-'}`} />
                        <Line label="Компания" value={connection.freight_load?.company?.name} />
                        {connection.freight_load?.id && (
                            <Link className="text-sm underline" href={route('loads.show', connection.freight_load.id)}>
                                Открыть карточку груза
                            </Link>
                        )}
                    </InfoSection>

                    <InfoSection title="Грузовладелец">
                        <Line label="Пользователь" value={connection.shipper?.name} />
                        <Line label="Компания" value={connection.shipper?.company?.name} />
                        <Line label="Телефон" value={connection.shipper?.phone || connection.shipper?.company?.phone} />
                        <Line label="Email" value={connection.shipper?.email || connection.shipper?.company?.email} />
                    </InfoSection>

                    <InfoSection title="Перевозчик">
                        <Line label="Пользователь" value={connection.carrier?.name} />
                        <Line label="Компания" value={connection.carrier?.company?.name} />
                        <Line label="Транспорт" value={connection.vehicle?.title} />
                        {connection.vehicle?.id && (
                            <Link className="text-sm underline" href={route('vehicles.show', connection.vehicle.id)}>
                                Открыть карточку транспорта
                            </Link>
                        )}
                    </InfoSection>
                </div>

                <div className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_380px]">
                    <section className="grid gap-4 rounded-md border p-4">
                        <div>
                            <h2 className="font-semibold">Управление соединением</h2>
                            <p className="text-sm text-muted-foreground">
                                Статус отражает только диспетчерскую работу и не меняет автоматически статус груза.
                            </p>
                        </div>
                        <div className="grid gap-3 md:grid-cols-2">
                            <Line label="Способ связи" value={contactMethodLabels[connection.contact_method] ?? connection.contact_method} />
                            <Line label="Отклик" value={connection.bid?.id ? `#${connection.bid.id} · ${bidStatusLabels[connection.bid.status] ?? connection.bid.status}` : 'ещё не создан'} />
                            <Line label="Создано" value={formatDateTime(connection.created_at)} />
                            <Line label="Уведомлены" value={formatDateTime(connection.shipper_contacted_at || connection.carrier_contacted_at)} />
                            <Line label="Связаны" value={formatDateTime(connection.connected_at)} />
                            <Line label="Закрыто" value={formatDateTime(connection.closed_at)} />
                        </div>
                        <select value={status} onChange={(event) => setStatus(event.target.value)} className="rounded-md border bg-background px-3 py-2">
                            {Object.entries(statusLabels).map(([value, label]) => (
                                <option key={value} value={value}>{label}</option>
                            ))}
                        </select>
                        <textarea
                            value={internalComment}
                            onChange={(event) => setInternalComment(event.target.value)}
                            className="min-h-28 rounded-md border bg-background p-3 text-sm"
                            placeholder="Внутренний комментарий диспетчера"
                        />
                        <Button className="w-fit" onClick={update}>Обновить статус</Button>
                    </section>

                    <section className="rounded-md border border-amber-300 bg-amber-50 p-4 text-sm text-amber-950">
                        {disclaimer}
                    </section>
                </div>

                <section className="rounded-md border">
                    <div className="border-b p-4">
                        <h2 className="font-semibold">История изменений</h2>
                        <p className="text-sm text-muted-foreground">Последние действия по этому диспетчерскому соединению.</p>
                    </div>
                    <div className="grid gap-0">
                        {auditLogs.length === 0 && (
                            <div className="p-4 text-sm text-muted-foreground">История пока пустая.</div>
                        )}
                        {auditLogs.map((log) => (
                            <div key={log.id} className="grid gap-2 border-b p-4 last:border-b-0">
                                <div className="flex flex-wrap items-center justify-between gap-2">
                                    <div className="font-medium">{actionLabel(log.action)}</div>
                                    <div className="text-sm text-muted-foreground">{formatDateTime(log.created_at)}</div>
                                </div>
                                <div className="text-sm text-muted-foreground">
                                    {log.actor?.name || log.actor?.email || 'Система'}
                                </div>
                                <ChangeList oldValues={log.old_values_json} newValues={log.new_values_json} />
                            </div>
                        ))}
                    </div>
                </section>
            </div>
        </AuthenticatedLayout>
    );
}

function InfoSection({ title, children }: { title: string; children: ReactNode }) {
    return (
        <section className="grid gap-3 rounded-md border p-4">
            <h2 className="font-semibold">{title}</h2>
            <div className="grid gap-2">{children}</div>
        </section>
    );
}

function Line({ label, value }: { label: string; value?: ReactNode }) {
    return (
        <div>
            <div className="text-xs text-muted-foreground">{label}</div>
            <div className="text-sm">{value || '-'}</div>
        </div>
    );
}

function ChangeList({ oldValues, newValues }: { oldValues?: Record<string, unknown> | null; newValues?: Record<string, unknown> | null }) {
    const keys = Array.from(new Set([...Object.keys(oldValues ?? {}), ...Object.keys(newValues ?? {})]));

    if (keys.length === 0) {
        return null;
    }

    return (
        <div className="grid gap-1 text-sm">
            {keys.map((key) => (
                <div key={key} className="grid gap-1 rounded-md bg-muted/40 p-2 md:grid-cols-[180px_1fr]">
                    <div className="text-muted-foreground">{fieldLabels[key] ?? key}</div>
                    <div>
                        <span className="text-muted-foreground">{formatValue(oldValues?.[key])}</span>
                        <span className="px-2">→</span>
                        <span>{formatValue(newValues?.[key])}</span>
                    </div>
                </div>
            ))}
        </div>
    );
}

function actionLabel(action: string) {
    const labels: Record<string, string> = {
        'dispatcher_connection.created': 'Соединение создано',
        'dispatcher_connection.updated': 'Соединение обновлено',
    };

    return labels[action] ?? action;
}

function formatDateTime(value?: string | null) {
    if (!value) {
        return '-';
    }

    if (!value.includes('T')) {
        return value;
    }

    const date = new Date(value);

    return Number.isNaN(date.getTime()) ? value : date.toLocaleString('ru-RU');
}

function formatValue(value: unknown) {
    if (value === null || value === undefined || value === '') {
        return '-';
    }

    if (typeof value === 'string' && statusLabels[value]) {
        return statusLabels[value];
    }

    return String(value);
}
