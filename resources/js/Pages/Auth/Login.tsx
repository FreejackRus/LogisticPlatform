import { LoginForm } from '@/Components/LoginForm';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head } from '@inertiajs/react';

export default function Login({ status }: { status?: string }) {
    return (
        <GuestLayout>
            <Head title="Вход" />

            <LoginForm />

            {status && (
                <div className="mb-4 w-full text-center text-sm font-medium text-green-600">
                    {status}
                </div>
            )}
        </GuestLayout>
    );
}
