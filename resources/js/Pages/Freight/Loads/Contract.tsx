import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { formatDate } from '@/lib/utils';
import { Head, Link } from '@inertiajs/react';
import { CheckCircle2, Download, FileText, ShieldCheck } from 'lucide-react';

type Party = {
    name?: string | null;
    inn?: string | null;
    address?: string | null;
    contact?: string | null;
    phone?: string | null;
    email?: string | null;
};

type Props = {
    contract: {
        number: string;
        terms_version: string;
        generated_at: string;
        shipper: Party;
        carrier: Party;
        load: {
            id: number;
            title: string;
            description?: string | null;
            loading?: string | null;
            unloading?: string | null;
            loading_date?: string | null;
            unloading_date?: string | null;
            weight_kg?: number | null;
            volume_m3?: number | null;
            places_count?: number | null;
            status: string;
            confirmation_code?: string | null;
            completion_confirmed_at?: string | null;
        };
        vehicle: {
            title?: string | null;
            registration_number?: string | null;
            body_type?: string | null;
        };
        payment: {
            price?: number | null;
            currency?: string | null;
            payment_type?: string | null;
            payment_terms?: string | null;
        };
        signatures: {
            carrier_accepted_at?: string | null;
            shipper_signed_at?: string | null;
        };
    };
    downloadUrl: string;
    loadUrl: string;
    platformDisclaimer: string;
};

const paymentLabels: Record<string, string> = {
    negotiable: 'По договоренности',
    bank_transfer: 'Безналичный расчет',
    cash: 'Наличные',
    card: 'Карта',
};

const statusLabels: Record<string, string> = {
    draft: 'Черновик',
    active: 'Активен',
    in_progress: 'В работе',
    completed: 'Завершен',
    cancelled: 'Отменен',
    archived: 'Архив',
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

export default function Contract({ contract, downloadUrl, loadUrl, platformDisclaimer }: Props) {
    const price = contract.payment.price
        ? `${contract.payment.price.toLocaleString('ru-RU')} ${contract.payment.currency || 'RUB'}`
        : 'по договоренности';
    const isFullySigned = Boolean(contract.signatures.carrier_accepted_at && contract.signatures.shipper_signed_at);

    return (
        <AuthenticatedLayout breadcrumbs={[
            { title: 'Груз', href: loadUrl },
            { title: `Договор ${contract.number}` },
        ]}>
            <Head title={`Договор перевозки ${contract.number}`} />
            <div className="mx-auto grid max-w-6xl gap-5 px-3 py-4 sm:px-4 sm:py-6">
                <div className="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                    <div>
                        <div className="flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
                            <FileText className="size-4" />
                            <span>Договор перевозки</span>
                            <span>№{contract.number}</span>
                            <Badge variant={isFullySigned ? 'default' : 'secondary'}>
                                {isFullySigned ? 'Подписан сторонами' : 'Ожидает подписи'}
                            </Badge>
                        </div>
                        <h1 className="mt-2 text-2xl font-semibold">Договор перевозки груза</h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Сформирован {contract.generated_at}. Версия условий: {contract.terms_version}.
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button asChild variant="secondary">
                            <Link href={loadUrl}>К карточке груза</Link>
                        </Button>
                        <Button asChild>
                            <a href={downloadUrl}>
                                <Download className="size-4" />
                                Скачать PDF
                            </a>
                        </Button>
                    </div>
                </div>

                <section className="grid gap-3 md:grid-cols-2">
                    <SignatureStatus
                        title="Перевозчик принял условия"
                        value={contract.signatures.carrier_accepted_at}
                    />
                    <SignatureStatus
                        title="Заказчик выбрал перевозчика"
                        value={contract.signatures.shipper_signed_at}
                    />
                </section>

                <section className="grid gap-4 md:grid-cols-2">
                    <PartyCard title="Заказчик" party={contract.shipper} />
                    <PartyCard title="Перевозчик" party={contract.carrier} />
                </section>

                <section className="grid gap-4 rounded-md border p-4">
                    <div className="flex flex-wrap items-center justify-between gap-2">
                        <h2 className="font-semibold">Груз и маршрут</h2>
                        <Badge variant="secondary">{statusLabels[contract.load.status] ?? contract.load.status}</Badge>
                    </div>
                    <div className="grid gap-3 md:grid-cols-2">
                        <Line label="Груз" value={contract.load.title} />
                        <Line label="Описание" value={contract.load.description} />
                        <Line label="Погрузка" value={contract.load.loading} />
                        <Line label="Выгрузка" value={contract.load.unloading} />
                        <Line label="Дата погрузки" value={formatDate(contract.load.loading_date)} />
                        <Line label="Дата выгрузки" value={formatDate(contract.load.unloading_date)} />
                        <Line label="Вес / объем / мест" value={[
                            contract.load.weight_kg ? `${contract.load.weight_kg.toLocaleString('ru-RU')} кг` : null,
                            contract.load.volume_m3 ? `${contract.load.volume_m3} м3` : null,
                            contract.load.places_count ? `${contract.load.places_count} мест` : null,
                        ].filter(Boolean).join(' / ')} />
                    </div>
                </section>

                <section className="grid gap-4 md:grid-cols-2">
                    <div className="rounded-md border p-4">
                        <h2 className="font-semibold">Транспорт</h2>
                        <div className="mt-3 grid gap-2">
                            <Line label="Машина" value={contract.vehicle.title} />
                            <Line label="Госномер" value={contract.vehicle.registration_number} />
                            <Line label="Кузов" value={bodyTypeLabels[contract.vehicle.body_type || ''] ?? contract.vehicle.body_type} />
                        </div>
                    </div>
                    <div className="rounded-md border p-4">
                        <h2 className="font-semibold">Оплата</h2>
                        <div className="mt-3 grid gap-2">
                            <Line label="Стоимость" value={price} />
                            <Line label="Способ оплаты" value={paymentLabels[contract.payment.payment_type || ''] ?? contract.payment.payment_type} />
                            <Line label="Условия" value={contract.payment.payment_terms} />
                        </div>
                    </div>
                </section>

                <section className="grid gap-4 rounded-md border p-4">
                    <h2 className="font-semibold">Исполнение доставки</h2>
                    <div className="grid gap-3 md:grid-cols-2">
                        <Line label="Код подтверждения доставки" value={contract.load.confirmation_code} />
                        <Line label="Доставка подтверждена" value={contract.load.completion_confirmed_at || 'нет'} />
                    </div>
                </section>

                <section className="rounded-md border border-amber-300 bg-amber-50 p-4 text-sm leading-6 text-amber-950">
                    <div className="mb-2 flex items-center gap-2 font-medium">
                        <ShieldCheck className="size-4" />
                        Роль платформы
                    </div>
                    <p className="whitespace-pre-line">{platformDisclaimer}</p>
                </section>
            </div>
        </AuthenticatedLayout>
    );
}

function SignatureStatus({ title, value }: { title: string; value?: string | null }) {
    return (
        <section className="rounded-md border p-4">
            <div className="flex items-start gap-3">
                <CheckCircle2 className={`mt-0.5 size-5 ${value ? 'text-emerald-600' : 'text-muted-foreground'}`} />
                <div>
                    <h2 className="font-semibold">{title}</h2>
                    <p className="mt-1 text-sm text-muted-foreground">{value || 'подпись не зафиксирована'}</p>
                </div>
            </div>
        </section>
    );
}

function PartyCard({ title, party }: { title: string; party: Party }) {
    return (
        <section className="rounded-md border p-4">
            <h2 className="font-semibold">{title}</h2>
            <div className="mt-3 grid gap-2">
                <Line label="Наименование" value={party.name} />
                <Line label="ИНН" value={party.inn} />
                <Line label="Адрес" value={party.address} />
                <Line label="Контакт" value={party.contact} />
                <Line label="Телефон" value={party.phone} />
                <Line label="Email" value={party.email} />
            </div>
        </section>
    );
}

function Line({ label, value }: { label: string; value?: string | number | null }) {
    return (
        <div>
            <div className="text-xs text-muted-foreground">{label}</div>
            <div className="text-sm">{value || 'не указано'}</div>
        </div>
    );
}
