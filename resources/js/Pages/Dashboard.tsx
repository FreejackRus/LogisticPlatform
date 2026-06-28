import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

export default function Dashboard() {
    const actions = [
        {
            href: route('loads.index'),
            title: 'Грузы',
            description: 'Каталог заявок и откликов перевозчиков.',
        },
        {
            href: route('vehicles.index'),
            title: 'Перевозчики',
            description: 'Открытый каталог транспорта и компаний.',
        },
        {
            href: route('map'),
            title: 'Карта',
            description: 'Грузы, транспорт и маршруты на открытой карте.',
        },
    ];

    return (
        <AuthenticatedLayout breadcrumbs={[{ title: 'Панель' }]}>
            <Head title="Панель" />

            <div className="mx-auto grid max-w-7xl gap-4 px-4 py-8 sm:px-6 md:grid-cols-3 lg:px-8">
                {actions.map((action) => (
                    <Link
                        key={action.href}
                        href={action.href}
                        className="rounded-lg border bg-card p-5 text-card-foreground shadow-sm transition hover:border-primary"
                    >
                        <h2 className="text-base font-semibold">{action.title}</h2>
                        <p className="mt-2 text-sm text-muted-foreground">{action.description}</p>
                    </Link>
                ))}
            </div>
        </AuthenticatedLayout>
    );
}
