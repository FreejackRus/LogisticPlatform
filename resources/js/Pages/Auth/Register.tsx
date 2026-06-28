import ApplicationLogoBox from '@/Components/ApplicationLogoBox';
import InputError from '@/Components/InputError';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, useForm, usePage } from '@inertiajs/react';
import { FormEventHandler } from 'react';

export default function Register() {
    const { data, setData, post, processing, errors, reset } = useForm<{
        name: string;
        email: string;
        phone: string;
        role: 'shipper' | 'carrier';
        password: string;
        password_confirmation: string;
        agree_to_terms: boolean;
        agree_to_privacy: boolean;
        agree_to_platform_role: boolean;
    }>({
        name: '',
        email: '',
        phone: '',
        role: 'shipper',
        password: '',
        password_confirmation: '',
        agree_to_terms: false,
        agree_to_privacy: false,
        agree_to_platform_role: false,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post(route('register'), {
            onFinish: () => reset('password', 'password_confirmation'),
        });
    };

    const app = usePage().props.app;

    return (
        <GuestLayout>
            <Head title="Регистрация" />

            <form onSubmit={submit}>
                <div className="flex flex-col gap-6">
                    <div className="flex flex-col items-center gap-2">
                        <a
                            href="#"
                            className="flex flex-col items-center gap-2 font-medium"
                        >
                            <ApplicationLogoBox />
                            <span className="sr-only">{app.name}</span>
                        </a>
                        <h1 className="text-xl font-bold">
                            Регистрация в {app.name}
                        </h1>
                        <div className="text-center text-sm">
                            Уже есть аккаунт?{' '}
                            <a
                                href={route('login')}
                                className="underline underline-offset-4"
                            >
                                Войти
                            </a>
                        </div>
                    </div>
                    <div className="flex flex-col gap-6">
                        <div className="grid gap-2">
                            <Label htmlFor="name">Имя</Label>

                            <Input
                                id="name"
                                name="name"
                                value={data.name}
                                className="mt-1 block w-full"
                                autoComplete="name"
                                autoFocus={true}
                                onChange={(e) =>
                                    setData('name', e.target.value)
                                }
                                required
                            />

                            <InputError
                                message={errors.name}
                                className="mt-2"
                            />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="email">Эл. почта</Label>

                            <Input
                                id="email"
                                type="email"
                                name="email"
                                value={data.email}
                                className="mt-1 block w-full"
                                autoComplete="username"
                                onChange={(e) =>
                                    setData('email', e.target.value)
                                }
                                required
                            />

                            <InputError
                                message={errors.email}
                                className="mt-2"
                            />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="phone">Телефон</Label>

                            <Input
                                id="phone"
                                name="phone"
                                value={data.phone}
                                className="mt-1 block w-full"
                                autoComplete="tel"
                                onChange={(e) =>
                                    setData('phone', e.target.value)
                                }
                            />

                            <InputError
                                message={errors.phone}
                                className="mt-2"
                            />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="role">Роль</Label>
                            <select
                                id="role"
                                value={data.role}
                                onChange={(e) =>
                                    setData(
                                        'role',
                                        e.target.value as 'shipper' | 'carrier',
                                    )
                                }
                                className="rounded-md border border-input bg-background px-3 py-2 text-sm"
                            >
                                <option value="shipper">Грузовладелец</option>
                                <option value="carrier">Перевозчик</option>
                            </select>
                            <InputError
                                message={errors.role}
                                className="mt-2"
                            />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="password">Пароль</Label>

                            <Input
                                id="password"
                                type="password"
                                name="password"
                                value={data.password}
                                className="mt-1 block w-full"
                                autoComplete="new-password"
                                onChange={(e) =>
                                    setData('password', e.target.value)
                                }
                                required
                            />

                            <InputError
                                message={errors.password}
                                className="mt-2"
                            />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="password_confirmation">
                                Повторите пароль
                            </Label>

                            <Input
                                id="password_confirmation"
                                type="password"
                                name="password_confirmation"
                                value={data.password_confirmation}
                                className="mt-1 block w-full"
                                autoComplete="new-password"
                                onChange={(e) =>
                                    setData(
                                        'password_confirmation',
                                        e.target.value,
                                    )
                                }
                                required
                            />

                            <InputError
                                message={errors.password_confirmation}
                                className="mt-2"
                            />
                        </div>

                        <div className="flex items-start gap-2">
                            <input
                                id="agree_to_terms"
                                type="checkbox"
                                name="agree_to_terms"
                                checked={data.agree_to_terms}
                                onChange={(e) =>
                                    setData('agree_to_terms', e.target.checked)
                                }
                                className="mt-1 h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                required
                            />
                            <Label
                                htmlFor="agree_to_terms"
                                className="text-sm leading-relaxed"
                            >
                                Я принимаю{' '}
                                <a
                                    href={route('legal.terms')}
                                    className="text-blue-600 underline underline-offset-4 hover:text-blue-800"
                                >
                                    условия использования
                                </a>{' '}
                                платформы.{' '}
                                <a
                                    href={route('legal.disclaimer')}
                                    className="text-blue-600 underline underline-offset-4 hover:text-blue-800"
                                >
                                    Дисклеймер
                                </a>
                            </Label>
                        </div>

                        <InputError
                            message={errors.agree_to_terms}
                            className="mt-2"
                        />

                        <div className="flex items-start gap-2">
                            <input
                                id="agree_to_privacy"
                                type="checkbox"
                                name="agree_to_privacy"
                                checked={data.agree_to_privacy}
                                onChange={(e) =>
                                    setData('agree_to_privacy', e.target.checked)
                                }
                                className="mt-1 h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                required
                            />
                            <Label
                                htmlFor="agree_to_privacy"
                                className="text-sm leading-relaxed"
                            >
                                Я согласен на обработку персональных данных, контактных данных и данных, необходимых для заключения и исполнения договоров перевозки.
                            </Label>
                        </div>

                        <InputError
                            message={errors.agree_to_privacy}
                            className="mt-2"
                        />

                        <div className="flex items-start gap-2">
                            <input
                                id="agree_to_platform_role"
                                type="checkbox"
                                name="agree_to_platform_role"
                                checked={data.agree_to_platform_role}
                                onChange={(e) =>
                                    setData('agree_to_platform_role', e.target.checked)
                                }
                                className="mt-1 h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                required
                            />
                            <Label
                                htmlFor="agree_to_platform_role"
                                className="text-sm leading-relaxed"
                            >
                                Я понимаю, что платформа является информационным сервисом, не принимает оплату за перевозку и не становится стороной договора между заказчиком и перевозчиком.
                            </Label>
                        </div>

                        <InputError
                            message={errors.agree_to_platform_role}
                            className="mt-2"
                        />

                        <div className="flex items-center justify-end">
                            <Button
                                className="w-full"
                                disabled={processing || !data.agree_to_terms || !data.agree_to_privacy || !data.agree_to_platform_role}
                            >
                                Зарегистрироваться
                            </Button>
                        </div>
                    </div>
                </div>
            </form>
        </GuestLayout>
    );
}
