import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { FormEventHandler, ReactNode, useState } from 'react';

type AdminProps = {
    filters: Record<string, string | undefined>;
    stats: Record<string, number>;
    users: any[];
    companies: any[];
    loads: any[];
    vehicles: any[];
    connections: any[];
    complaints: any[];
};

const roleLabels: Record<string, string> = {
    admin: 'Админ',
    shipper: 'Грузовладелец',
    carrier: 'Перевозчик',
    dispatcher: 'Диспетчер',
};

const companyStatusLabels: Record<string, string> = {
    not_verified: 'Не проверена',
    pending: 'На проверке',
    verified: 'Проверена',
    rejected: 'Отклонена',
};

const loadStatusLabels: Record<string, string> = {
    draft: 'Черновик',
    active: 'Активен',
    in_progress: 'В работе',
    completed: 'Завершен',
    cancelled: 'Отменен',
    archived: 'Архив',
};

const complaintStatusLabels: Record<string, string> = {
    new: 'Новая',
    in_review: 'В работе',
    resolved: 'Решена',
    rejected: 'Отклонена',
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

export default function Index({ filters, stats, users, companies, loads, vehicles, connections, complaints }: AdminProps) {
    const [form, setForm] = useState({
        q: filters.q ?? '',
        user_role: filters.user_role ?? '',
        company_status: filters.company_status ?? '',
        load_status: filters.load_status ?? '',
        vehicle_state: filters.vehicle_state ?? '',
        complaint_status: filters.complaint_status ?? '',
    });

    const submitFilters: FormEventHandler = (event) => {
        event.preventDefault();
        router.get(route('admin.freight.index'), clean(form), { preserveScroll: true });
    };

    const resetFilters = () => {
        router.get(route('admin.freight.index'), {}, { preserveScroll: true });
    };

    return (
        <AuthenticatedLayout breadcrumbs={[{ title: 'Админка биржи' }]}>
            <Head title="Админка биржи" />
            <div className="grid gap-6 px-4 py-6">
                <div>
                    <h1 className="text-2xl font-semibold">Админ-панель биржи</h1>
                    <p className="text-sm text-muted-foreground">
                        Обзор платформы, модерация и переходы в карточки объектов с аудитом и редактированием.
                    </p>
                </div>

                <section className="grid gap-3 md:grid-cols-4 xl:grid-cols-7">
                    {[
                        ['Пользователи', stats.users],
                        ['Компании', stats.companies],
                        ['Грузы', stats.loads],
                        ['Транспорт', stats.vehicles],
                        ['Соединения', stats.connections],
                        ['Жалобы', stats.complaints],
                        ['Открытые жалобы', stats.openComplaints],
                    ].map(([label, value]) => (
                        <div key={label} className="rounded-md border p-4">
                            <p className="text-sm text-muted-foreground">{label}</p>
                            <p className="text-3xl font-semibold">{value}</p>
                        </div>
                    ))}
                </section>

                <form onSubmit={submitFilters} className="grid gap-3 rounded-md border p-4 md:grid-cols-3 xl:grid-cols-6">
                    <Input value={form.q} onChange={(event) => setForm({ ...form, q: event.target.value })} placeholder="Поиск" />
                    <Select value={form.user_role} onChange={(value) => setForm({ ...form, user_role: value })}>
                        <option value="">Все роли</option>
                        {Object.entries(roleLabels).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                    </Select>
                    <Select value={form.company_status} onChange={(value) => setForm({ ...form, company_status: value })}>
                        <option value="">Все компании</option>
                        {Object.entries(companyStatusLabels).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                    </Select>
                    <Select value={form.load_status} onChange={(value) => setForm({ ...form, load_status: value })}>
                        <option value="">Все грузы</option>
                        {Object.entries(loadStatusLabels).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                    </Select>
                    <Select value={form.vehicle_state} onChange={(value) => setForm({ ...form, vehicle_state: value })}>
                        <option value="">Весь транспорт</option>
                        <option value="available">Доступен</option>
                        <option value="hidden">Скрыт с карты</option>
                        <option value="online">Онлайн</option>
                        <option value="offline">Офлайн</option>
                    </Select>
                    <Select value={form.complaint_status} onChange={(value) => setForm({ ...form, complaint_status: value })}>
                        <option value="">Все жалобы</option>
                        {Object.entries(complaintStatusLabels).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                    </Select>
                    <div className="flex gap-2 md:col-span-3 xl:col-span-6">
                        <Button type="submit">Применить</Button>
                        <Button type="button" variant="secondary" onClick={resetFilters}>Сбросить</Button>
                    </div>
                </form>

                <div className="grid gap-4 xl:grid-cols-2">
                    <AdminSection title="Пользователи" count={users.length}>
                        {users.map((user) => (
                            <ObjectCard
                                key={user.id}
                                href={route('admin.freight.users.show', user.id)}
                                title={user.name}
                                subtitle={[user.email, user.phone || 'телефон не указан'].join(' · ')}
                                meta={<Badge variant="secondary">{roleLabels[user.role] ?? user.role}</Badge>}
                                footer={user.is_blocked ? 'Заблокирован' : user.is_active ? 'Активен' : 'Отключен'}
                            />
                        ))}
                    </AdminSection>

                    <AdminSection title="Компании" count={companies.length}>
                        {companies.map((company) => (
                            <ObjectCard
                                key={company.id}
                                href={route('admin.freight.companies.show', company.id)}
                                title={company.name}
                                subtitle={[
                                    company.inn ? `ИНН ${company.inn}` : 'ИНН не указан',
                                    company.phone || company.email || 'контакты не указаны',
                                ].join(' · ')}
                                meta={<Badge variant={company.verification_status === 'verified' ? 'default' : 'secondary'}>{companyStatusLabels[company.verification_status] ?? company.verification_status}</Badge>}
                                footer={company.user?.name || 'владелец не указан'}
                            />
                        ))}
                    </AdminSection>

                    <AdminSection title="Грузы" count={loads.length}>
                        {loads.map((load) => (
                            <ObjectCard
                                key={load.id}
                                href={route('admin.freight.loads.show', load.id)}
                                title={load.title}
                                subtitle={`${load.loading_city || '-'} - ${load.unloading_city || '-'}`}
                                meta={<Badge variant="secondary">{loadStatusLabels[load.status] ?? load.status}</Badge>}
                                footer={`${load.company?.name || 'компания не указана'} · откликов: ${load.bids_count ?? 0}`}
                            />
                        ))}
                    </AdminSection>

                    <AdminSection title="Транспорт" count={vehicles.length}>
                        {vehicles.map((vehicle) => (
                            <ObjectCard
                                key={vehicle.id}
                                href={route('admin.freight.vehicles.show', vehicle.id)}
                                title={vehicle.title}
                                subtitle={[
                                    vehicle.registration_number || 'номер не указан',
                                    vehicle.body_type || 'кузов не указан',
                                    vehicle.current_city || 'город не указан',
                                ].join(' · ')}
                                meta={<Badge variant={vehicle.is_available ? 'default' : 'secondary'}>{vehicle.is_available ? 'Доступен' : 'Недоступен'}</Badge>}
                                footer={`${vehicle.company?.name || 'компания не указана'} · ${vehicle.is_online ? 'онлайн' : 'офлайн'} · ${vehicle.is_location_visible ? 'на карте' : 'скрыт'}`}
                            />
                        ))}
                    </AdminSection>

                    <AdminSection title="Жалобы" count={complaints.length}>
                        {complaints.map((complaint) => (
                            <ObjectCard
                                key={complaint.id}
                                href={route('complaints.index')}
                                title={complaint.type}
                                subtitle={complaint.message || 'без описания'}
                                meta={<Badge variant={complaint.status === 'new' || complaint.status === 'in_review' ? 'default' : 'secondary'}>{complaintStatusLabels[complaint.status] ?? complaint.status}</Badge>}
                                footer={complaint.reporter?.name || complaint.reporter?.email || 'заявитель не указан'}
                            />
                        ))}
                    </AdminSection>

                    <AdminSection title="Диспетчерские соединения" count={connections.length}>
                        {connections.map((connection) => (
                            <ObjectCard
                                key={connection.id}
                                href={route('dispatcher.connections.show', connection.id)}
                                title={`Соединение #${connection.id}`}
                                subtitle={connection.freight_load?.title || 'груз не указан'}
                                meta={<Badge variant="secondary">{connectionStatusLabels[connection.status] ?? connection.status}</Badge>}
                                footer={[connection.dispatcher?.name, connection.carrier?.name].filter(Boolean).join(' · ') || 'участники не указаны'}
                            />
                        ))}
                    </AdminSection>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

function clean(values: Record<string, string>) {
    return Object.fromEntries(Object.entries(values).filter(([, value]) => value !== ''));
}

function AdminSection({ title, count, children }: { title: string; count: number; children: ReactNode }) {
    return (
        <section className="rounded-md border">
            <div className="flex items-center justify-between gap-3 border-b p-4">
                <h2 className="font-semibold">{title}</h2>
                <Badge variant="secondary">{count}</Badge>
            </div>
            <div className="grid gap-3 p-3">
                {count > 0 ? children : <div className="rounded-md border border-dashed p-6 text-center text-sm text-muted-foreground">Нет записей по текущим фильтрам.</div>}
            </div>
        </section>
    );
}

function ObjectCard({ href, title, subtitle, meta, footer }: { href: string; title: string; subtitle?: string; meta?: ReactNode; footer?: string }) {
    return (
        <Link href={href} className="block rounded-md border p-4 hover:bg-muted">
            <div className="flex items-start justify-between gap-3">
                <div className="min-w-0">
                    <h3 className="truncate font-medium">{title}</h3>
                    {subtitle && <p className="mt-1 line-clamp-2 text-sm text-muted-foreground">{subtitle}</p>}
                </div>
                {meta}
            </div>
            {footer && <p className="mt-3 text-xs text-muted-foreground">{footer}</p>}
        </Link>
    );
}

function Select({ value, onChange, children }: { value: string; onChange: (value: string) => void; children: ReactNode }) {
    return (
        <select value={value} onChange={(event) => onChange(event.target.value)} className="rounded-md border bg-background px-3 py-2 text-sm">
            {children}
        </select>
    );
}
