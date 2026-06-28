import { Button } from '@/Components/ui/button';
import { Head, Link, usePage } from '@inertiajs/react';

type Props = {
    canLogin: boolean;
    canRegister: boolean;
    disclaimer: string;
};

export default function Home({ canLogin, canRegister, disclaimer }: Props) {
    const app = usePage().props.app;

    return (
        <div className="min-h-screen bg-background text-foreground">
            <Head title="Биржа грузов" />
            <header className="border-b">
                <div className="mx-auto flex max-w-7xl items-center justify-between px-4 py-4">
                    <Link href={route('home')} className="text-lg font-semibold">
                        {app.name}
                    </Link>
                    <nav className="flex items-center gap-2 text-sm">
                        <Link href={route('loads.index')}>Грузы</Link>
                        <Link href={route('map')}>Карта</Link>
                        {canLogin && <Link href={route('login')}>Войти</Link>}
                        {canRegister && (
                            <Button asChild size="sm">
                                <Link href={route('register')}>Регистрация</Link>
                            </Button>
                        )}
                    </nav>
                </div>
            </header>
            <main className="mx-auto grid max-w-7xl gap-8 px-4 py-8 lg:grid-cols-[1.1fr_0.9fr]">
                <section className="flex min-h-[520px] flex-col justify-center gap-6 rounded-md bg-zinc-950 p-8 text-white">
                    <p className="text-sm uppercase tracking-wide text-emerald-300">
                        Бесплатный информационный агрегатор РФ
                    </p>
                    <h1 className="max-w-3xl text-4xl font-semibold leading-tight md:text-6xl">
                        Биржа грузов и транспорта
                    </h1>
                    <p className="max-w-2xl text-lg text-zinc-200">
                        Грузовладельцы публикуют грузы, перевозчики добавляют
                        транспорт и откликаются, диспетчер помогает вручную
                        связать стороны без комиссий и платежей платформе.
                    </p>
                    <div className="flex flex-wrap gap-3">
                        <Button asChild>
                            <Link href={route('loads.index')}>Смотреть грузы</Link>
                        </Button>
                        <Button asChild variant="secondary">
                            <Link href={route('map')}>Открыть карту</Link>
                        </Button>
                    </div>
                </section>
                <section className="grid content-start gap-4">
                    {[
                        ['Грузы', 'Публикация маршрута, веса, объема, цены и контактов.'],
                        ['Транспорт', 'Доступность, координаты, online-статус и видимость на карте.'],
                        ['Диспетчер', 'Ручные соединения сторон с уведомлениями и журналом действий.'],
                    ].map(([title, text]) => (
                        <article key={title} className="rounded-md border p-5">
                            <h2 className="text-lg font-semibold">{title}</h2>
                            <p className="mt-2 text-sm text-muted-foreground">{text}</p>
                        </article>
                    ))}
                    <article className="rounded-md border border-amber-300 bg-amber-50 p-5 text-sm leading-6 text-amber-950">
                        {disclaimer}
                    </article>
                </section>
            </main>
        </div>
    );
}
