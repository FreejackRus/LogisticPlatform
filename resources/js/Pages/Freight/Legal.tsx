import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

type Props = {
    title: string;
    disclaimer: string;
};

export default function Legal({ title, disclaimer }: Props) {
    return (
        <AuthenticatedLayout breadcrumbs={[{ title }]}>
            <Head title={title} />
            <div className="mx-auto max-w-4xl px-4 py-6">
                <h1 className="text-2xl font-semibold">{title}</h1>
                <div className="mt-6 whitespace-pre-line rounded-md border bg-card p-5 leading-7">
                    {disclaimer}
                </div>
                <Link className="mt-6 inline-block text-sm underline" href={route('home')}>
                    На главную
                </Link>
            </div>
        </AuthenticatedLayout>
    );
}
