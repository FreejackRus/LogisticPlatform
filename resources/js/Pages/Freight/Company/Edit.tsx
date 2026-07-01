import InputError from '@/Components/InputError';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler, ReactNode } from 'react';

type Company = {
    name?: string;
    short_name?: string;
    inn?: string;
    kpp?: string;
    ogrn?: string;
    tax_system?: string;
    legal_address?: string;
    actual_address?: string;
    director_name?: string;
    contact_person?: string;
    bank_name?: string;
    bank_bik?: string;
    bank_account?: string;
    correspondent_account?: string;
    phone?: string;
    email?: string;
    website?: string;
    description?: string;
    carrier_profile_type?: string;
    allows_carrier_members?: boolean;
    carrier_members?: Array<{ id: number; name: string; email: string; pivot?: { role?: string; status?: string } }>;
    verification_status?: string;
    verification_comment?: string;
    verified_at?: string | null;
    rejected_at?: string | null;
};

const statusLabels: Record<string, string> = {
    not_verified: 'Не проверена',
    pending: 'На проверке',
    verified: 'Проверена',
    rejected: 'Отклонена',
};

const taxSystems = [
    ['general', 'ОСНО'],
    ['simplified_income', 'УСН доходы'],
    ['simplified_income_expense', 'УСН доходы минус расходы'],
    ['patent', 'Патент'],
    ['self_employed', 'Самозанятый'],
];

type Options = {
    carrier_profile_types: Record<string, string>;
};

export default function Edit({ company, options, isCarrier, canManageCarrierMembers }: { company: Company | null; options: Options; isCarrier: boolean; canManageCarrierMembers: boolean }) {
    const { data, setData, post, processing, errors } = useForm({
        name: company?.name ?? '',
        short_name: company?.short_name ?? '',
        inn: company?.inn ?? '',
        kpp: company?.kpp ?? '',
        ogrn: company?.ogrn ?? '',
        tax_system: company?.tax_system ?? '',
        legal_address: company?.legal_address ?? '',
        actual_address: company?.actual_address ?? '',
        director_name: company?.director_name ?? '',
        contact_person: company?.contact_person ?? '',
        bank_name: company?.bank_name ?? '',
        bank_bik: company?.bank_bik ?? '',
        bank_account: company?.bank_account ?? '',
        correspondent_account: company?.correspondent_account ?? '',
        phone: company?.phone ?? '',
        email: company?.email ?? '',
        website: company?.website ?? '',
        description: company?.description ?? '',
        carrier_profile_type: company?.carrier_profile_type ?? 'individual',
        allows_carrier_members: Boolean(company?.allows_carrier_members),
    });
    const memberForm = useForm({
        email: '',
        role: 'driver',
    });

    const submit: FormEventHandler = (event) => {
        event.preventDefault();
        post(route('freight.company.update'));
    };

    const addMember: FormEventHandler = (event) => {
        event.preventDefault();
        memberForm.post(route('freight.company.carriers.store'), {
            preserveScroll: true,
            onSuccess: () => memberForm.reset(),
        });
    };

    const status = company?.verification_status ?? 'not_verified';

    return (
        <AuthenticatedLayout breadcrumbs={[{ title: 'Компания' }]}>
            <Head title="Компания" />
            <form onSubmit={submit} className="mx-auto grid max-w-6xl gap-6 px-4 py-6">
                <div className="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold">Профиль компании</h1>
                        <p className="max-w-3xl text-sm text-muted-foreground">
                            Эти данные используются для допуска к сделкам, контактов с контрагентами и модерации на бирже.
                        </p>
                    </div>
                    <Badge className="w-fit" variant={status === 'verified' ? 'default' : 'secondary'}>
                        {statusLabels[status] ?? status}
                    </Badge>
                </div>

                {company?.verification_comment && (
                    <div className="rounded-md border border-amber-300 bg-amber-50 p-4 text-sm text-amber-950">
                        <div className="font-medium">Комментарий модератора</div>
                        <div>{company.verification_comment}</div>
                    </div>
                )}

                <Section title="Основные данные">
                    <Field
                        id="name"
                        label="Полное наименование"
                        value={data.name}
                        error={errors.name}
                        required
                        onChange={(value) => setData('name', value)}
                    />
                    <Field
                        id="short_name"
                        label="Короткое наименование"
                        value={data.short_name}
                        error={errors.short_name}
                        onChange={(value) => setData('short_name', value)}
                    />
                    <Field
                        id="director_name"
                        label="Руководитель"
                        value={data.director_name}
                        error={errors.director_name}
                        onChange={(value) => setData('director_name', value)}
                    />
                    <Field
                        id="contact_person"
                        label="Ответственный за логистику"
                        value={data.contact_person}
                        error={errors.contact_person}
                        onChange={(value) => setData('contact_person', value)}
                    />
                    {isCarrier && (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="carrier_profile_type">Тип перевозчика</Label>
                                <select
                                    id="carrier_profile_type"
                                    value={data.carrier_profile_type}
                                    onChange={(event) => {
                                        setData('carrier_profile_type', event.target.value);
                                        setData('allows_carrier_members', event.target.value === 'company');
                                    }}
                                    className="rounded-md border bg-background px-3 py-2 text-sm"
                                >
                                    {Object.entries(options.carrier_profile_types).map(([value, label]) => (
                                        <option key={value} value={value}>{label}</option>
                                    ))}
                                </select>
                                <InputError message={errors.carrier_profile_type} />
                            </div>
                            <label className="flex items-center gap-2 text-sm">
                                <input
                                    type="checkbox"
                                    checked={data.allows_carrier_members}
                                    onChange={(event) => setData('allows_carrier_members', event.target.checked)}
                                    disabled={data.carrier_profile_type !== 'company'}
                                />
                                Компания может подключать перевозчиков
                            </label>
                        </>
                    )}
                </Section>

                <Section title="Юридические данные">
                    <Field id="inn" label="ИНН" value={data.inn} error={errors.inn} onChange={(value) => setData('inn', value)} />
                    <Field id="kpp" label="КПП" value={data.kpp} error={errors.kpp} onChange={(value) => setData('kpp', value)} />
                    <Field id="ogrn" label="ОГРН / ОГРНИП" value={data.ogrn} error={errors.ogrn} onChange={(value) => setData('ogrn', value)} />
                    <div className="grid gap-2">
                        <Label htmlFor="tax_system">Система налогообложения</Label>
                        <select
                            id="tax_system"
                            value={data.tax_system}
                            onChange={(event) => setData('tax_system', event.target.value)}
                            className="rounded-md border bg-background px-3 py-2 text-sm"
                        >
                            <option value="">Не указана</option>
                            {taxSystems.map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                        </select>
                        <InputError message={errors.tax_system} />
                    </div>
                    <Field
                        id="legal_address"
                        label="Юридический адрес"
                        value={data.legal_address}
                        error={errors.legal_address}
                        onChange={(value) => setData('legal_address', value)}
                    />
                    <Field
                        id="actual_address"
                        label="Фактический адрес"
                        value={data.actual_address}
                        error={errors.actual_address}
                        onChange={(value) => setData('actual_address', value)}
                    />
                </Section>

                <Section title="Банковские реквизиты">
                    <Field id="bank_name" label="Банк" value={data.bank_name} error={errors.bank_name} onChange={(value) => setData('bank_name', value)} />
                    <Field id="bank_bik" label="БИК" value={data.bank_bik} error={errors.bank_bik} onChange={(value) => setData('bank_bik', value)} />
                    <Field
                        id="bank_account"
                        label="Расчётный счёт"
                        value={data.bank_account}
                        error={errors.bank_account}
                        onChange={(value) => setData('bank_account', value)}
                    />
                    <Field
                        id="correspondent_account"
                        label="Корреспондентский счёт"
                        value={data.correspondent_account}
                        error={errors.correspondent_account}
                        onChange={(value) => setData('correspondent_account', value)}
                    />
                </Section>

                <Section title="Контакты и описание">
                    <Field id="phone" label="Телефон" value={data.phone} error={errors.phone} onChange={(value) => setData('phone', value)} />
                    <Field id="email" label="Email" value={data.email} error={errors.email} onChange={(value) => setData('email', value)} />
                    <Field id="website" label="Сайт" value={data.website} error={errors.website} onChange={(value) => setData('website', value)} />
                    <div className="grid gap-2 md:col-span-2">
                        <Label htmlFor="description">Описание</Label>
                        <textarea
                            id="description"
                            value={data.description}
                            onChange={(event) => setData('description', event.target.value)}
                            className="min-h-28 rounded-md border bg-background p-3 text-sm"
                        />
                        <InputError message={errors.description} />
                    </div>
                </Section>

                <div className="flex flex-col gap-2 border-t pt-5 sm:flex-row sm:items-center">
                    <Button className="w-fit" disabled={processing}>Сохранить и отправить на проверку</Button>
                    <p className="text-sm text-muted-foreground">
                        Изменение юридических или банковских реквизитов вернёт компанию на модерацию.
                    </p>
                </div>
            </form>

            {canManageCarrierMembers && (
                <section className="mx-auto mb-8 grid max-w-6xl gap-4 px-4">
                    <div>
                        <h2 className="text-base font-semibold">Перевозчики компании</h2>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Добавляйте только активных перевозчиков без собственной компании и без участия в другой активной компании.
                        </p>
                    </div>
                    <form onSubmit={addMember} className="grid gap-3 rounded-md border p-4 md:grid-cols-[1fr_auto_auto]">
                        <Input
                            type="email"
                            value={memberForm.data.email}
                            onChange={(event) => memberForm.setData('email', event.target.value)}
                            placeholder="email перевозчика"
                            required
                        />
                        <select
                            value={memberForm.data.role}
                            onChange={(event) => memberForm.setData('role', event.target.value)}
                            className="rounded-md border bg-background px-3 py-2 text-sm"
                        >
                            <option value="driver">Водитель</option>
                            <option value="manager">Менеджер</option>
                        </select>
                        <Button disabled={memberForm.processing}>Добавить</Button>
                        <InputError className="md:col-span-3" message={memberForm.errors.email || memberForm.errors.role} />
                    </form>
                    <div className="grid gap-2">
                        {(company?.carrier_members ?? []).map((member) => (
                            <div key={member.id} className="flex flex-wrap items-center justify-between gap-3 rounded-md border p-3 text-sm">
                                <span>{member.name} · {member.email}</span>
                                <Badge variant="secondary">{member.pivot?.role === 'manager' ? 'Менеджер' : 'Водитель'}</Badge>
                            </div>
                        ))}
                    </div>
                </section>
            )}
        </AuthenticatedLayout>
    );
}

function Section({ title, children }: { title: string; children: ReactNode }) {
    return (
        <section className="grid gap-4 border-t pt-5">
            <h2 className="text-base font-semibold">{title}</h2>
            <div className="grid gap-4 md:grid-cols-2">{children}</div>
        </section>
    );
}

function Field({
    id,
    label,
    value,
    error,
    required,
    onChange,
}: {
    id: string;
    label: string;
    value: string;
    error?: string;
    required?: boolean;
    onChange: (value: string) => void;
}) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={id}>{label}</Label>
            <Input id={id} value={value} onChange={(event) => onChange(event.target.value)} required={required} />
            <InputError message={error} />
        </div>
    );
}
