import InputError from '@/Components/InputError';
import PrimaryButton from '@/Components/PrimaryButton';
import { Avatar, AvatarFallback, AvatarImage } from '@/Components/ui/avatar';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/Components/ui/select';
import { PageProps, Timezone } from '@/types';
import { Transition } from '@headlessui/react';
import { Link, useForm, usePage } from '@inertiajs/react';
import axios from 'axios';
import { FormEventHandler, useCallback, useEffect, useState } from 'react';
import { useDropzone } from 'react-dropzone';

interface Language {
    code: string;
    name: string;
    native_name: string;
}

export default function UpdateProfileInformation({
    mustVerifyEmail,
    status,
    className = '',
}: {
    mustVerifyEmail: boolean;
    status?: string;
    className?: string;
}) {
    const user = usePage<PageProps>().props.auth.user;

    if (!user) {
        return null;
    }

    const [timezones, setTimezones] = useState<Timezone[]>(
        user.timezone ? [{ id: 0, name: user.timezone }] : [],
    );
    const [languages, setLanguages] = useState<Language[]>([]);
    const [selectedLanguage, setSelectedLanguage] = useState<string>(
        user.language_preference || 'ru',
    );

    const { data, setData, post, errors, processing, recentlySuccessful } =
        useForm({
            name: user.name,
            email: user.email,
            photo: null as File | null,
            removePhoto: false as boolean,
            timezone: user.timezone,
            language_preference: selectedLanguage,
        });

    const [photoPreview, setPhotoPreview] = useState<string | null>(
        user.profile_photo_url || null,
    );

    // Reset photo preview to server URL after successful upload
    useEffect(() => {
        if (recentlySuccessful && user.profile_photo_url) {
            setPhotoPreview(user.profile_photo_url);
            setData('photo', null); // Clear the file from form data
        }
    }, [recentlySuccessful, user.profile_photo_url, setData]);

    useEffect(() => {
        fetch(route('timezones.search'), {
            method: 'GET',
            headers: {
                Accept: 'application/json',
            },
        })
            .then((res) => res.json())
            .then((data: Timezone[]) => {
                setTimezones(data);
            });

        axios
            .get<Language[]>(route('languages.index'))
            .then((response) => {
                setLanguages(response.data);
                setSelectedLanguage(user.language_preference || 'ru');
            })
            .catch((error) => {
                console.error('Failed to fetch languages:', error);
            });
    }, [user.language_preference]);

    const onDrop = useCallback(
        (acceptedFiles: File[]) => {
            const file = acceptedFiles[0];
            if (file) {
                setData('removePhoto', false);
                setData('photo', file);
                const reader = new FileReader();
                reader.onload = (e) => {
                    setPhotoPreview(e.target?.result as string);
                };
                reader.readAsDataURL(file);
            }
        },
        [setData],
    );

    const { getRootProps, getInputProps, isDragActive } = useDropzone({
        onDrop,
        accept: {
            'image/*': ['.jpeg', '.jpg', '.png', '.gif'],
        },
        maxFiles: 1,
        maxSize: 2097152, // 2MB
    });

    const removePhoto = () => {
        setData('removePhoto', true);
        setData('photo', null);
        setPhotoPreview(null);
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post(route('profile.update'), {});
    };

    const handleLanguageChange = (value: string) => {
        setSelectedLanguage(value);
        setData('language_preference', value);
    };

    return (
        <section className={className}>
            <header>
                <h2 className="text-lg font-medium text-gray-900 dark:text-gray-100">
                    Данные профиля
                </h2>

                <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    Обновите имя, email, фото, язык и часовой пояс аккаунта.
                </p>
            </header>

            <form onSubmit={submit} className="mt-6 space-y-6">
                <div>
                    <Label htmlFor="photo">Фото</Label>

                    <div className="mt-2 flex items-center gap-6">
                        {photoPreview ? (
                            <div className="flex flex-col items-center gap-2">
                                <Avatar className="h-20 w-20">
                                    <AvatarImage
                                        src={photoPreview}
                                        alt={user.name}
                                    />
                                    <AvatarFallback>
                                        {user.name
                                            .split(' ')
                                            .map((n) => n[0])
                                            .join('')
                                            .toUpperCase()}
                                    </AvatarFallback>
                                </Avatar>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={removePhoto}
                                >
                                    Удалить фото
                                </Button>
                            </div>
                        ) : (
                            <Avatar className="h-20 w-20">
                                <AvatarFallback>
                                    {user.name
                                        .split(' ')
                                        .map((n) => n[0])
                                        .join('')
                                        .toUpperCase()}
                                </AvatarFallback>
                            </Avatar>
                        )}

                        <div
                            {...getRootProps()}
                            className={`flex cursor-pointer flex-col items-center justify-center rounded-md border-2 border-dashed p-6 ${
                                isDragActive
                                    ? 'border-primary bg-primary/10'
                                    : 'border-gray-300 dark:border-gray-700'
                            }`}
                        >
                            <input {...getInputProps()} id="photo" />
                            <div className="text-center">
                                <p className="text-sm text-gray-600 dark:text-gray-400">
                                    {isDragActive
                                        ? 'Отпустите файл здесь'
                                        : 'Перетащите фото профиля или нажмите для выбора'}
                                </p>
                                <p className="mt-1 text-xs text-gray-500 dark:text-gray-500">
                                    PNG, JPG, GIF до 2 МБ
                                </p>
                            </div>
                        </div>
                    </div>

                    <InputError className="mt-2" message={errors.photo} />
                </div>

                <div>
                    <Label htmlFor="name">Имя</Label>

                    <Input
                        id="name"
                        className="mt-1 block w-full"
                        value={data.name}
                        onChange={(e) => setData('name', e.target.value)}
                        required
                        autoFocus={true}
                        autoComplete="name"
                    />

                    <InputError className="mt-2" message={errors.name} />
                </div>

                <div>
                    <Label htmlFor="email">Эл. почта</Label>

                    <Input
                        id="email"
                        type="email"
                        className="mt-1 block w-full"
                        value={data.email}
                        onChange={(e) => setData('email', e.target.value)}
                        required
                        autoComplete="username"
                    />

                    <InputError className="mt-2" message={errors.email} />
                </div>

                <div>
                    <Label htmlFor="timezone">Часовой пояс</Label>
                    <Select
                        defaultValue={user.timezone ?? undefined}
                        onValueChange={(value) => setData('timezone', value)}
                    >
                        <SelectTrigger>
                            <SelectValue placeholder="Выберите часовой пояс" />
                        </SelectTrigger>
                        <SelectContent>
                            {timezones.map((timezone) => (
                                <SelectItem
                                    key={timezone.name}
                                    value={timezone.name}
                                >
                                    {timezone.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                <div>
                    <Label htmlFor="language">Язык</Label>
                    <Select
                        value={selectedLanguage}
                        onValueChange={handleLanguageChange}
                    >
                        <SelectTrigger>
                            <SelectValue>
                                {languages.find(
                                    (lang) => lang.code === selectedLanguage,
                                )?.native_name || 'Выберите язык'}
                            </SelectValue>
                        </SelectTrigger>
                        <SelectContent>
                            {languages.map((language) => (
                                <SelectItem
                                    key={language.code}
                                    value={language.code}
                                >
                                    {language.native_name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                {mustVerifyEmail && user.email_verified_at === null && (
                    <div>
                        <p className="mt-2 text-sm text-gray-800 dark:text-gray-200">
                            Ваш email не подтвержден.
                            <Link
                                href={route('verification.send')}
                                method="post"
                                as="button"
                                className="rounded-md text-sm text-gray-600 underline hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:text-gray-400 dark:hover:text-gray-100 dark:focus:ring-offset-gray-800"
                            >
                                Отправить письмо подтверждения повторно.
                            </Link>
                        </p>

                        {status === 'verification-link-sent' && (
                            <div className="mt-2 text-sm font-medium text-green-600 dark:text-green-400">
                                Новая ссылка подтверждения отправлена на ваш email.
                            </div>
                        )}
                    </div>
                )}

                <div className="flex items-center gap-4">
                    <PrimaryButton disabled={processing}>Сохранить</PrimaryButton>

                    <Transition
                        show={recentlySuccessful}
                        enter="transition ease-in-out"
                        enterFrom="opacity-0"
                        leave="transition ease-in-out"
                        leaveTo="opacity-0"
                    >
                        <p className="text-sm text-gray-600 dark:text-gray-400">
                            Сохранено.
                        </p>
                    </Transition>
                </div>
            </form>
        </section>
    );
}
