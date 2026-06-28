import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { ReactNode } from 'react';

type AuditLog = {
    id: number;
    action: string;
    old_values_json?: Record<string, unknown> | null;
    new_values_json?: Record<string, unknown> | null;
    created_at: string;
    actor?: { name?: string; email?: string } | null;
};

type Props = {
    type: 'user' | 'company' | 'load' | 'vehicle';
    title: string;
    entity: any;
    auditLogs: AuditLog[];
};

const roleLabels: Record<string, string> = {
    admin: 'Админ',
    shipper: 'Грузовладелец',
    carrier: 'Перевозчик',
    dispatcher: 'Диспетчер',
};

const statusLabels: Record<string, string> = {
    not_verified: 'Не проверена',
    pending: 'На проверке',
    verified: 'Проверена',
    rejected: 'Отклонена',
    draft: 'Черновик',
    active: 'Активен',
    in_progress: 'В работе',
    completed: 'Завершён',
    cancelled: 'Отменён',
    archived: 'Архив',
};

const vehicleTypeLabels: Record<string, string> = {
    truck: 'Грузовик',
    van: 'Фургон',
    tractor: 'Тягач',
    refrigerator: 'Рефрижератор',
};

const bodyTypeLabels: Record<string, string> = {
    tent: 'Тент',
    refrigerator: 'Рефрижератор',
    isothermal: 'Изотерм',
    board: 'Бортовой',
    container: 'Контейнер',
    van: 'Фургон',
    open_platform: 'Открытая площадка',
};

const fieldLabels: Record<string, string> = {
    name: 'Имя',
    email: 'Email',
    phone: 'Телефон',
    role: 'Роль',
    is_active: 'Активен',
    is_blocked: 'Заблокирован',
    verification_status: 'Проверка',
    verification_comment: 'Комментарий проверки',
    status: 'Статус',
    is_urgent: 'Срочный',
    is_featured: 'Выделен',
    is_available: 'Доступен',
    is_location_visible: 'Показывать на карте',
    is_online: 'Онлайн',
    title: 'Название',
    vehicle_type: 'Тип транспорта',
    body_type: 'Тип кузова',
    registration_number: 'Госномер',
    capacity_kg: 'Грузоподъемность',
    volume_m3: 'Объем',
    current_city: 'Город',
    current_region: 'Регион',
};

const actionLabels: Record<string, string> = {
    'user.updated': 'Пользователь обновлен',
    'company.updated': 'Компания обновлена',
    'load.moderated': 'Груз промодерирован',
    'vehicle.moderated': 'Транспорт обновлен',
    'vehicle.map_visibility_updated': 'Видимость на карте обновлена',
    'complaint.updated': 'Жалоба обновлена',
};

const bidStatusLabels: Record<string, string> = {
    pending: 'Ожидает',
    accepted: 'Принят',
    rejected: 'Отклонен',
    cancelled: 'Отменен',
};

const deliveryEventLabels: Record<string, string> = {
    carrier_selected: 'Перевозчик выбран',
    en_route_to_pickup: 'В пути к погрузке',
    arrived_to_pickup: 'Прибыл на погрузку',
    loaded: 'Груз загружен',
    en_route_to_delivery: 'В пути к выгрузке',
    arrived_to_delivery: 'Прибыл на выгрузку',
    unloaded: 'Груз выгружен',
    delivery_confirmed: 'Доставка подтверждена',
    issue_reported: 'Проблема',
};

export default function EntityShow({ type, title, entity, auditLogs }: Props) {
    return (
        <AuthenticatedLayout breadcrumbs={[{ title: 'Админка биржи', href: route('admin.freight.index') }, { title }]}>
            <Head title={title} />
            <div className="mx-auto grid max-w-6xl gap-6 px-4 py-6">
                <div className="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold">{title}</h1>
                        <p className="text-sm text-muted-foreground">
                            Карточка администратора для проверки данных, связанных объектов и истории действий.
                        </p>
                    </div>
                    <Button asChild variant="secondary"><Link href={route('admin.freight.index')}>Назад в админку</Link></Button>
                </div>

                {type === 'user' && <UserDetails user={entity} />}
                {type === 'company' && <CompanyDetails company={entity} />}
                {type === 'load' && <LoadDetails load={entity} />}
                {type === 'vehicle' && <VehicleDetails vehicle={entity} />}

                <AuditHistory auditLogs={auditLogs} />
            </div>
        </AuthenticatedLayout>
    );
}

function UserDetails({ user }: { user: any }) {
    const form = useForm({
        name: user.name ?? '',
        email: user.email ?? '',
        phone: user.phone ?? '',
        role: user.role ?? 'carrier',
        is_active: Boolean(user.is_active),
        is_blocked: Boolean(user.is_blocked),
    });

    return (
        <div className="grid gap-4 lg:grid-cols-3">
            <InfoSection title="Пользователь">
                <Line label="Имя" value={user.name} />
                <Line label="Email" value={user.email} />
                <Line label="Телефон" value={user.phone} />
                <Line label="Роль" value={roleLabels[user.role] ?? user.role} />
                <Line label="Статус" value={user.is_blocked ? 'Заблокирован' : user.is_active ? 'Активен' : 'Отключён'} />
                <Line label="Последний вход" value={formatDateTime(user.last_login_at)} />
            </InfoSection>
            <InfoSection title="Компания">
                {user.company ? (
                    <>
                        <Line label="Название" value={user.company.name} />
                        <Line label="ИНН" value={user.company.inn} />
                        <Line label="Проверка" value={statusLabels[user.company.verification_status] ?? user.company.verification_status} />
                        <Link className="text-sm underline" href={route('admin.freight.companies.show', user.company.id)}>Открыть компанию</Link>
                    </>
                ) : <Empty />}
            </InfoSection>
            <RelatedList title="Связанные объекты">
                <Line label="Грузы" value={user.loads?.length ?? 0} />
                <Line label="Транспорт" value={user.vehicles?.length ?? 0} />
                <Line label="Отклики" value={user.bids?.length ?? 0} />
            </RelatedList>
            <form
                onSubmit={(event) => {
                    event.preventDefault();
                    form.patch(route('admin.freight.users.update', user.id));
                }}
                className="grid gap-3 rounded-md border p-4 lg:col-span-3"
            >
                <h2 className="font-semibold">Редактирование пользователя</h2>
                <div className="grid gap-3 md:grid-cols-3">
                    <AdminInput label="Имя" value={form.data.name} onChange={(value) => form.setData('name', value)} />
                    <AdminInput label="Email" value={form.data.email} onChange={(value) => form.setData('email', value)} />
                    <AdminInput label="Телефон" value={form.data.phone} onChange={(value) => form.setData('phone', value)} />
                    <select value={form.data.role} onChange={(event) => form.setData('role', event.target.value)} className="rounded-md border bg-background px-3 py-2 text-sm">
                        {Object.entries(roleLabels).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                    </select>
                    <Check label="Активен" checked={form.data.is_active} onChange={(value) => form.setData('is_active', value)} />
                    <Check label="Заблокирован" checked={form.data.is_blocked} onChange={(value) => form.setData('is_blocked', value)} />
                </div>
                <Button className="w-fit" disabled={form.processing}>Сохранить пользователя</Button>
            </form>
        </div>
    );
}

function CompanyDetails({ company }: { company: any }) {
    const form = useForm({
        name: company.name ?? '',
        short_name: company.short_name ?? '',
        inn: company.inn ?? '',
        kpp: company.kpp ?? '',
        ogrn: company.ogrn ?? '',
        carrier_profile_type: company.carrier_profile_type ?? 'individual',
        phone: company.phone ?? '',
        email: company.email ?? '',
        legal_address: company.legal_address ?? '',
        actual_address: company.actual_address ?? '',
        description: company.description ?? '',
        verification_status: company.verification_status ?? 'not_verified',
        verification_comment: company.verification_comment ?? '',
        is_blocked: Boolean(company.is_blocked),
    });

    return (
        <div className="grid gap-4 lg:grid-cols-3">
            <InfoSection title="Компания">
                <Line label="Название" value={company.name} />
                <Line label="Короткое название" value={company.short_name} />
                <Line label="Тип" value={roleLabels[company.type] ?? company.type} />
                <Line label="Проверка" value={<Badge>{statusLabels[company.verification_status] ?? company.verification_status}</Badge>} />
                <Line label="Комментарий проверки" value={company.verification_comment} />
            </InfoSection>
            <InfoSection title="Реквизиты">
                <Line label="ИНН" value={company.inn} />
                <Line label="КПП" value={company.kpp} />
                <Line label="ОГРН" value={company.ogrn} />
                <Line label="Адрес" value={company.legal_address} />
                <Line label="Банк" value={company.bank_name} />
                <Line label="БИК" value={company.bank_bik} />
            </InfoSection>
            <RelatedList title="Связанные объекты">
                <Line label="Владелец" value={company.user?.name} />
                {company.user?.id && <Link className="text-sm underline" href={route('admin.freight.users.show', company.user.id)}>Открыть пользователя</Link>}
                <Line label="Грузы" value={company.loads?.length ?? 0} />
                <Line label="Транспорт" value={company.vehicles?.length ?? 0} />
            </RelatedList>
            <form
                onSubmit={(event) => {
                    event.preventDefault();
                    form.patch(route('admin.freight.companies.update', company.id));
                }}
                className="grid gap-3 rounded-md border p-4 lg:col-span-3"
            >
                <h2 className="font-semibold">Редактирование компании</h2>
                <div className="grid gap-3 md:grid-cols-3">
                    <AdminInput label="Название" value={form.data.name} onChange={(value) => form.setData('name', value)} />
                    <AdminInput label="Короткое название" value={form.data.short_name} onChange={(value) => form.setData('short_name', value)} />
                    <AdminInput label="ИНН" value={form.data.inn} onChange={(value) => form.setData('inn', value)} />
                    <AdminInput label="КПП" value={form.data.kpp} onChange={(value) => form.setData('kpp', value)} />
                    <AdminInput label="ОГРН" value={form.data.ogrn} onChange={(value) => form.setData('ogrn', value)} />
                    <AdminInput label="Телефон" value={form.data.phone} onChange={(value) => form.setData('phone', value)} />
                    <AdminInput label="Email" value={form.data.email} onChange={(value) => form.setData('email', value)} />
                    <AdminInput label="Юр. адрес" value={form.data.legal_address} onChange={(value) => form.setData('legal_address', value)} />
                    <AdminInput label="Факт. адрес" value={form.data.actual_address} onChange={(value) => form.setData('actual_address', value)} />
                    <select value={form.data.carrier_profile_type} onChange={(event) => form.setData('carrier_profile_type', event.target.value)} className="rounded-md border bg-background px-3 py-2 text-sm">
                        <option value="individual">Индивидуальный перевозчик</option>
                        <option value="company">Транспортная компания</option>
                    </select>
                    <select value={form.data.verification_status} onChange={(event) => form.setData('verification_status', event.target.value)} className="rounded-md border bg-background px-3 py-2 text-sm">
                        {['not_verified', 'pending', 'verified', 'rejected'].map((status) => <option key={status} value={status}>{statusLabels[status]}</option>)}
                    </select>
                    <Check label="Заблокирована" checked={form.data.is_blocked} onChange={(value) => form.setData('is_blocked', value)} />
                </div>
                <textarea value={form.data.verification_comment} onChange={(event) => form.setData('verification_comment', event.target.value)} className="min-h-20 rounded-md border bg-background p-3 text-sm" placeholder="Комментарий проверки" />
                <textarea value={form.data.description} onChange={(event) => form.setData('description', event.target.value)} className="min-h-20 rounded-md border bg-background p-3 text-sm" placeholder="Описание" />
                <Button className="w-fit" disabled={form.processing}>Сохранить компанию</Button>
            </form>
        </div>
    );
}

function LoadDetails({ load }: { load: any }) {
    const acceptedBid = load.bids?.find((bid: any) => bid.status === 'accepted');

    return (
        <div className="grid gap-4 lg:grid-cols-3">
            <InfoSection title="Груз">
                <Line label="Название" value={load.title} />
                <Line label="Маршрут" value={`${load.loading_city ?? '-'} - ${load.unloading_city ?? '-'}`} />
                <Line label="Статус" value={statusLabels[load.status] ?? load.status} />
                <Line label="Цена" value={load.price ? `${load.price} ${load.price_currency ?? 'RUB'}` : null} />
                <Line label="Отклики" value={load.bids_count ?? load.bids?.length ?? 0} />
                <Link className="text-sm underline" href={route('loads.show', load.id)}>Открыть публичную карточку</Link>
                {acceptedBid && <Link className="text-sm underline" href={route('loads.contract', load.id)}>Открыть договор</Link>}
            </InfoSection>
            <InfoSection title="Заказчик">
                <Line label="Пользователь" value={load.shipper?.name} />
                <Line label="Компания" value={load.company?.name} />
                {load.company?.id && <Link className="text-sm underline" href={route('admin.freight.companies.show', load.company.id)}>Открыть компанию</Link>}
            </InfoSection>
            <RelatedList title="Активность">
                <Line label="Отклики" value={load.bids?.length ?? 0} />
                <Line label="Диспетчерские соединения" value={load.dispatcher_connections?.length ?? 0} />
                <Line label="Просмотры" value={load.views_count ?? 0} />
            </RelatedList>
            <RelatedList title="Отклики">
                <RelatedCollection
                    items={load.bids ?? []}
                    empty="Откликов пока нет."
                    render={(bid: any) => (
                        <RelatedItem
                            key={bid.id}
                            title={`${bid.carrier?.company?.name || bid.carrier?.name || 'Перевозчик'} · ${bidStatusLabels[bid.status] ?? bid.status}`}
                            subtitle={[
                                bid.vehicle?.title,
                                bid.price ? `${Number(bid.price).toLocaleString('ru-RU')} ${bid.price_currency ?? 'RUB'}` : null,
                                bid.contract_signed_at ? `договор подписан ${formatDateTime(bid.contract_signed_at)}` : null,
                            ].filter(Boolean).join(' · ')}
                            href={bid.vehicle?.id ? route('admin.freight.vehicles.show', bid.vehicle.id) : undefined}
                        />
                    )}
                />
            </RelatedList>
            <RelatedList title="Диспетчерские соединения">
                <RelatedCollection
                    items={load.dispatcher_connections ?? []}
                    empty="Соединений пока нет."
                    render={(connection: any) => (
                        <RelatedItem
                            key={connection.id}
                            title={`Соединение #${connection.id}`}
                            subtitle={[connection.dispatcher?.name, connection.carrier?.name, connection.status].filter(Boolean).join(' · ')}
                            href={route('dispatcher.connections.show', connection.id)}
                        />
                    )}
                />
            </RelatedList>
            <RelatedList title="События доставки">
                <RelatedCollection
                    items={load.delivery_events ?? []}
                    empty="Событий доставки пока нет."
                    render={(event: any) => (
                        <RelatedItem
                            key={event.id}
                            title={deliveryEventLabels[event.type] ?? event.type}
                            subtitle={[formatDateTime(event.created_at), event.actor?.name, event.note].filter(Boolean).join(' · ')}
                        />
                    )}
                />
            </RelatedList>
        </div>
    );
}

function VehicleDetails({ vehicle }: { vehicle: any }) {
    const form = useForm({
        title: vehicle.title ?? '',
        vehicle_type: vehicle.vehicle_type ?? 'truck',
        body_type: vehicle.body_type ?? '',
        registration_number: vehicle.registration_number ?? '',
        capacity_kg: vehicle.capacity_kg ?? '',
        volume_m3: vehicle.volume_m3 ?? '',
        current_city: vehicle.current_city ?? '',
        current_region: vehicle.current_region ?? '',
        is_available: Boolean(vehicle.is_available),
        is_location_visible: Boolean(vehicle.is_location_visible),
        is_online: Boolean(vehicle.is_online),
    });

    return (
        <div className="grid gap-4 lg:grid-cols-3">
            <InfoSection title="Транспорт">
                <Line label="Название" value={vehicle.title} />
                <Line label="Госномер" value={vehicle.registration_number} />
                <Line label="Прицеп" value={vehicle.trailer_number} />
                <Line label="Кузов" value={bodyTypeLabels[vehicle.body_type] ?? vehicle.body_type} />
                <Line label="Грузоподъёмность" value={vehicle.capacity_kg ? `${vehicle.capacity_kg} кг` : null} />
                <Link className="text-sm underline" href={route('vehicles.show', vehicle.id)}>Открыть публичную карточку</Link>
            </InfoSection>
            <InfoSection title="Перевозчик">
                <Line label="Пользователь" value={vehicle.carrier?.name} />
                <Line label="Компания" value={vehicle.company?.name} />
                <Line label="Водитель" value={vehicle.assigned_driver?.name} />
                {vehicle.company?.id && <Link className="text-sm underline" href={route('admin.freight.companies.show', vehicle.company.id)}>Открыть компанию</Link>}
            </InfoSection>
            <InfoSection title="Состояние">
                <Line label="Доступен" value={vehicle.is_available ? 'Да' : 'Нет'} />
                <Line label="На карте" value={vehicle.is_location_visible ? 'Да' : 'Нет'} />
                <Line label="Онлайн" value={vehicle.is_online ? 'Да' : 'Нет'} />
                <Line label="Город" value={vehicle.current_city} />
                <Line label="Последняя геолокация" value={formatDateTime(vehicle.last_location_at)} />
            </InfoSection>
            <RelatedList title="Отклики и перевозки">
                <RelatedCollection
                    items={vehicle.bids ?? []}
                    empty="По этому транспорту пока нет откликов."
                    render={(bid: any) => (
                        <RelatedItem
                            key={bid.id}
                            title={bid.freight_load?.title || `Отклик #${bid.id}`}
                            subtitle={[
                                bid.freight_load ? `${bid.freight_load.loading_city ?? '-'} - ${bid.freight_load.unloading_city ?? '-'}` : null,
                                bidStatusLabels[bid.status] ?? bid.status,
                                bid.freight_load?.company?.name,
                            ].filter(Boolean).join(' · ')}
                            href={bid.freight_load?.id ? route('admin.freight.loads.show', bid.freight_load.id) : undefined}
                        />
                    )}
                />
            </RelatedList>
            <form
                onSubmit={(event) => {
                    event.preventDefault();
                    form.patch(route('admin.freight.vehicles.update', vehicle.id));
                }}
                className="grid gap-3 rounded-md border p-4 lg:col-span-3"
            >
                <h2 className="font-semibold">Редактирование транспорта</h2>
                <div className="grid gap-3 md:grid-cols-3">
                    <AdminInput label="Название" value={form.data.title} onChange={(value) => form.setData('title', value)} />
                    <AdminSelect label="Тип транспорта" value={form.data.vehicle_type} onChange={(value) => form.setData('vehicle_type', value)} options={vehicleTypeLabels} />
                    <AdminSelect label="Кузов" value={form.data.body_type} onChange={(value) => form.setData('body_type', value)} options={bodyTypeLabels} />
                    <AdminInput label="Госномер" value={form.data.registration_number} onChange={(value) => form.setData('registration_number', value)} />
                    <AdminInput label="Грузоподъемность" value={String(form.data.capacity_kg)} onChange={(value) => form.setData('capacity_kg', value)} />
                    <AdminInput label="Объем" value={String(form.data.volume_m3)} onChange={(value) => form.setData('volume_m3', value)} />
                    <AdminInput label="Город" value={form.data.current_city} onChange={(value) => form.setData('current_city', value)} />
                    <AdminInput label="Регион" value={form.data.current_region} onChange={(value) => form.setData('current_region', value)} />
                    <Check label="Доступен" checked={form.data.is_available} onChange={(value) => form.setData('is_available', value)} />
                    <Check label="На карте" checked={form.data.is_location_visible} onChange={(value) => form.setData('is_location_visible', value)} />
                    <Check label="Онлайн" checked={form.data.is_online} onChange={(value) => form.setData('is_online', value)} />
                </div>
                <Button className="w-fit" disabled={form.processing}>Сохранить транспорт</Button>
            </form>
        </div>
    );
}

function AuditHistory({ auditLogs }: { auditLogs: AuditLog[] }) {
    return (
        <section className="rounded-md border">
            <div className="border-b p-4">
                <h2 className="font-semibold">История действий</h2>
                <p className="text-sm text-muted-foreground">Последние изменения по объекту с автором и значениями до/после.</p>
            </div>
            {auditLogs.length === 0 && <div className="p-4 text-sm text-muted-foreground">История пока пустая.</div>}
            {auditLogs.map((log) => (
                <div key={log.id} className="grid gap-2 border-b p-4 last:border-b-0">
                    <div className="flex flex-wrap items-center justify-between gap-2">
                        <div className="font-medium">{actionLabels[log.action] ?? log.action}</div>
                        <div className="text-sm text-muted-foreground">{formatDateTime(log.created_at)}</div>
                    </div>
                    <div className="text-sm text-muted-foreground">{log.actor?.name || log.actor?.email || 'Система'}</div>
                    <AuditChanges oldValues={log.old_values_json} newValues={log.new_values_json} />
                </div>
            ))}
        </section>
    );
}

function AuditChanges({ oldValues, newValues }: { oldValues?: Record<string, unknown> | null; newValues?: Record<string, unknown> | null }) {
    const keys = Array.from(new Set([...Object.keys(oldValues ?? {}), ...Object.keys(newValues ?? {})]));

    if (keys.length === 0) {
        return <div className="text-sm text-muted-foreground">Изменения не зафиксированы.</div>;
    }

    return (
        <div className="grid gap-2 text-sm">
            {keys.map((key) => (
                <div key={key} className="grid gap-1 rounded-md bg-muted/40 p-2 md:grid-cols-[220px_1fr]">
                    <div className="text-muted-foreground">{fieldLabels[key] ?? key}</div>
                    <div className="min-w-0">
                        <span className="text-muted-foreground">{formatAuditValue(key, oldValues?.[key])}</span>
                        <span className="px-2">→</span>
                        <span>{formatAuditValue(key, newValues?.[key])}</span>
                    </div>
                </div>
            ))}
        </div>
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

function RelatedList({ title, children }: { title: string; children: ReactNode }) {
    return <InfoSection title={title}>{children}</InfoSection>;
}

function RelatedCollection<T>({ items, empty, render }: { items: T[]; empty: string; render: (item: T) => ReactNode }) {
    if (items.length === 0) {
        return <div className="text-sm text-muted-foreground">{empty}</div>;
    }

    return <div className="grid gap-2">{items.map(render)}</div>;
}

function RelatedItem({ title, subtitle, href }: { title: string; subtitle?: string; href?: string }) {
    const content = (
        <>
            <div className="font-medium">{title}</div>
            {subtitle && <div className="mt-1 text-xs text-muted-foreground">{subtitle}</div>}
        </>
    );

    if (href) {
        return <Link href={href} className="rounded-md border p-3 text-sm hover:bg-muted">{content}</Link>;
    }

    return <div className="rounded-md border p-3 text-sm">{content}</div>;
}

function Line({ label, value }: { label: string; value?: ReactNode }) {
    return (
        <div>
            <div className="text-xs text-muted-foreground">{label}</div>
            <div className="text-sm">{value || '-'}</div>
        </div>
    );
}

function Empty() {
    return <div className="text-sm text-muted-foreground">Нет данных.</div>;
}

function AdminInput({ label, value, onChange }: { label: string; value: string; onChange: (value: string) => void }) {
    return (
        <div className="grid gap-1">
            <Label>{label}</Label>
            <Input value={value} onChange={(event) => onChange(event.target.value)} />
        </div>
    );
}

function AdminSelect({ label, value, onChange, options }: { label: string; value: string; onChange: (value: string) => void; options: Record<string, string> }) {
    return (
        <div className="grid gap-1">
            <Label>{label}</Label>
            <select value={value} onChange={(event) => onChange(event.target.value)} className="rounded-md border bg-background px-3 py-2 text-sm">
                <option value="">Не указано</option>
                {Object.entries(options).map(([optionValue, label]) => (
                    <option key={optionValue} value={optionValue}>{label}</option>
                ))}
            </select>
        </div>
    );
}

function Check({ label, checked, onChange }: { label: string; checked: boolean; onChange: (value: boolean) => void }) {
    return (
        <label className="flex items-center gap-2 text-sm">
            <input type="checkbox" checked={checked} onChange={(event) => onChange(event.target.checked)} />
            {label}
        </label>
    );
}

function formatDateTime(value?: string | null) {
    if (!value) {
        return '-';
    }

    return new Date(value).toLocaleString('ru-RU');
}

function formatAuditValue(key: string, value: unknown) {
    if (value === null || value === undefined || value === '') {
        return '-';
    }

    if (typeof value === 'boolean') {
        return value ? 'Да' : 'Нет';
    }

    const text = String(value);

    if (key === 'role') {
        return roleLabels[text] ?? text;
    }

    if (key === 'status' || key === 'verification_status') {
        return statusLabels[text] ?? text;
    }

    if (key === 'vehicle_type') {
        return vehicleTypeLabels[text] ?? text;
    }

    if (key === 'body_type') {
        return bodyTypeLabels[text] ?? text;
    }

    return text;
}
