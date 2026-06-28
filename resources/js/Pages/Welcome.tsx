import { InteractiveGridPattern } from '@/Components/ui/interactive-grid-pattern';
import { cn } from '@/lib/utils';
import { PageProps } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';

export default function Welcome({ auth }: PageProps) {
    const { name: appName } = usePage().props.app;

    return (
        <>
            <Head title="Welcome" />
            <div className="relative min-h-screen bg-gray-50 dark:bg-black">
                {/* Background Pattern */}
                <InteractiveGridPattern
                    width={40}
                    height={40}
                    className={cn(
                        '[mask-image:radial-gradient(400px_circle_at_center,white,transparent)]',
                        'inset-x-0 inset-y-[-30%] h-[200%] skew-y-12',
                        'fixed inset-0 h-screen w-screen opacity-75',
                    )}
                    squares={[40, 40]}
                />

                {/* Content */}
                <div className="relative flex min-h-screen flex-col items-center justify-center p-4">
                    {/* Hero Section */}
                    <div className="text-center">
                        <h1 className="mb-6 text-4xl font-bold tracking-tight text-gray-900 dark:text-white sm:text-6xl">
                            {appName}
                        </h1>

                        {/* Auth Links */}
                        <div className="flex flex-row items-center justify-center gap-4">
                            {auth.user ? (
                                <Link
                                    href={route('dashboard')}
                                    className="rounded-lg bg-gray-900 px-6 py-3 text-sm font-medium text-white transition-colors hover:bg-gray-700 dark:bg-white dark:text-gray-900 dark:hover:bg-gray-100"
                                >
                                    Dashboard
                                </Link>
                            ) : (
                                <>
                                    <Link
                                        href={route('login')}
                                        className="rounded-lg bg-gray-900 px-6 py-3 text-sm font-medium text-white transition-colors hover:bg-gray-700 dark:bg-white dark:text-gray-900 dark:hover:bg-gray-100"
                                    >
                                        Log in
                                    </Link>
                                    <Link
                                        href={route('register')}
                                        className="rounded-lg border border-gray-900 bg-white px-6 py-3 text-sm font-medium text-gray-900 transition-colors hover:bg-gray-100 dark:border-white dark:bg-gray-900 dark:text-white dark:hover:bg-gray-800"
                                    >
                                        Register
                                    </Link>
                                </>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}
