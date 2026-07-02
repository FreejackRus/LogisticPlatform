import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

type ComplaintItem = {
    id: number;
    type: string;
    status: string;
    message: string;
    admin_comment?: string | null;
    created_at?: string | null;
    target_user?: {
        name?: string | null;
        role?: string | null;
    } | null;
    context?: {
        load_title?: string | null;
        load_url?: string | null;
        bid_id?: number | null;
        dispatcher_connection_id?: number | null;
    };
};

type Props = {
    complaints: {
        data: ComplaintItem[];
    };
};

const typeLabels: Record<string, string> = {
    fraud: 'Мошенничество',
    spam: 'Спам',
    wrong_contacts: 'Неверные контакты',
    no_show: 'Неявка',
    payment_issue: 'Проблема с оплатой',
    rude_behavior: 'Грубое поведение',
    other: 'Другое',
};

const statusLabels: Record<string, string> = {
    new: 'Новая',
    in_review: 'В работе',
    resolved: 'Решена',
    rejected: 'Отклонена',
};

export default function Complaints({ complaints }: Props) {
    const { data, setData, post, processing, reset, errors } = useForm({
        type: 'other',
        message: '',
    });

    const submit: FormEventHandler = (event) => {
        event.preventDefault();
        post(route('complaints.store'), {
            preserveScroll: true,
            onSuccess: () => reset('message'),
        });
    };

    return (
        <AuthenticatedLayout breadcrumbs={[{ title: 'Жалобы' }]}>
            <Head title="Жалобы" />
            <div className="mx-auto grid max-w-5xl gap-5 px-4 py-6">
                <div>
                    <h1 className="text-2xl font-semibold">Жалобы</h1>
                    <p className="text-sm text-muted-foreground">Сообщите о проблеме с контактами, поведением или подозрительной заявкой.</p>
                </div>
                <form onSubmit={submit} className="grid gap-3 rounded-md border p-4">
                    <select value={data.type} onChange={(event) => setData('type', event.target.value)} className="rounded-md border bg-background px-3 py-2">
                        {Object.entries(typeLabels).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                    </select>
                    <textarea
                        value={data.message}
                        onChange={(event) => setData('message', event.target.value)}
                        className="min-h-28 rounded-md border bg-background p-3 text-sm"
                        placeholder="Опишите проблему"
                        required
                    />
                    {errors.message && <p className="text-sm text-destructive">{errors.message}</p>}
                    <Button className="w-fit" disabled={processing}>Отправить</Button>
                </form>
                <div className="grid gap-3">
                    {complaints.data.map((item) => (
                        <div key={item.id} className="rounded-md border p-4 text-sm">
                            <div className="flex flex-wrap items-center justify-between gap-2">
                                <div>
                                    <p className="font-medium">{typeLabels[item.type] ?? item.type}</p>
                                    {item.created_at && <p className="text-xs text-muted-foreground">{item.created_at}</p>}
                                </div>
                                <Badge variant="secondary">{statusLabels[item.status] ?? item.status}</Badge>
                            </div>
                            {(item.context?.load_title || item.target_user) && (
                                <div className="mt-3 flex flex-wrap gap-2 text-xs text-muted-foreground">
                                    {item.context?.load_title && (
                                        item.context.load_url ? (
                                            <Link className="rounded bg-muted px-2 py-1 hover:underline" href={item.context.load_url}>
                                                {item.context.load_title}
                                            </Link>
                                        ) : (
                                            <span className="rounded bg-muted px-2 py-1">{item.context.load_title}</span>
                                        )
                                    )}
                                    {item.target_user?.name && (
                                        <span className="rounded bg-muted px-2 py-1">
                                            {item.target_user.name}
                                            {item.target_user.role ? ` · ${item.target_user.role}` : ''}
                                        </span>
                                    )}
                                    {item.context?.bid_id && <span className="rounded bg-muted px-2 py-1">Отклик #{item.context.bid_id}</span>}
                                    {item.context?.dispatcher_connection_id && (
                                        <span className="rounded bg-muted px-2 py-1">Соединение #{item.context.dispatcher_connection_id}</span>
                                    )}
                                </div>
                            )}
                            <p className="mt-2">{item.message}</p>
                            {item.admin_comment && (
                                <div className="mt-3 rounded-md bg-muted p-3">
                                    <p className="font-medium">Комментарий администратора</p>
                                    <p className="mt-1">{item.admin_comment}</p>
                                </div>
                            )}
                        </div>
                    ))}
                    {complaints.data.length === 0 && (
                        <div className="rounded-md border p-6 text-center text-sm text-muted-foreground">Жалоб пока нет.</div>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
