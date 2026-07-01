import {
    Bell,
    Building,
    ClipboardList,
    Home,
    Map,
    MessageCircle,
    Package,
    Truck,
    Users,
} from 'lucide-react';
import * as React from 'react';
import { useState } from 'react';

import GeneralFeedbackModal from '@/Components/GeneralFeedbackModal';
import { NavUser } from '@/Components/NavUser';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarGroup,
    SidebarHeader,
    SidebarMenuButton,
    SidebarRail,
} from '@/Components/ui/sidebar';
import { Link, usePage } from '@inertiajs/react';

type NavItem = {
    href: string;
    label: string;
    icon: React.ElementType;
    active: boolean;
    badge?: number;
};

export function AppSidebar({ ...props }: React.ComponentProps<typeof Sidebar>) {
    const { auth } = usePage().props as any;
    const user = auth?.user;
    const unreadNotificationsCount = Number(auth?.unread_notifications_count ?? 0);
    const [isFeedbackModalOpen, setIsFeedbackModalOpen] = useState(false);

    if (!user) {
        const publicItems: NavItem[] = [
            { href: route('loads.index'), label: 'Грузы', icon: Package, active: route().current('loads.index') || route().current('loads.show') },
            { href: route('map'), label: 'Карта', icon: Map, active: route().current('map') },
        ];

        return (
            <Sidebar collapsible="icon" {...props}>
                <SidebarHeader>
                    <div className="flex h-10 items-center gap-2 px-2 text-sm font-semibold">
                        <Truck className="size-4" />
                        <span>Биржа грузов</span>
                    </div>
                </SidebarHeader>
                <SidebarContent>
                    <SidebarGroup>
                        {[...publicItems, { href: route('vehicles.index'), label: 'Перевозчики', icon: Truck, active: route().current('vehicles.index') }].map((item) => (
                            <SidebarMenuButton
                                key={item.href}
                                asChild
                                isActive={item.active}
                                tooltip={item.label}
                            >
                                <Link href={item.href}>
                                    <item.icon />
                                    <span>{item.label}</span>
                                </Link>
                            </SidebarMenuButton>
                        ))}
                        <SidebarMenuButton asChild tooltip="Войти">
                            <Link href={route('login')}>
                                <Users />
                                <span>Войти</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarGroup>
                </SidebarContent>
                <SidebarRail />
            </Sidebar>
        );
    }

    const userRole = user.role ?? '';
    const isCarrierCompanyDriver = Boolean(user.is_carrier_company_driver);
    const publicItems: NavItem[] = [
        ...(!isCarrierCompanyDriver
            ? [{ href: route('loads.index'), label: 'Грузы', icon: Package, active: route().current('loads.index') || route().current('loads.show') }]
            : []),
        { href: route('map'), label: 'Карта', icon: Map, active: route().current('map') },
    ];
    const roleHomeRoute = userRole === 'admin'
        ? route('admin.freight.index')
        : userRole === 'dispatcher'
            ? route('dispatcher.index')
            : userRole === 'carrier'
                ? route('carrier.deliveries.index')
                : userRole === 'shipper'
                    ? route('loads.mine')
                    : route('dashboard');

    const items: NavItem[] = [
        {
            href: roleHomeRoute,
            label: 'Панель',
            icon: Home,
            active: route().current('dashboard')
                || route().current('carrier.deliveries.*')
                || route().current('vehicles.mine')
                || route().current('dispatcher.index')
                || route().current('admin.freight.index')
                || route().current('loads.mine'),
        },
        ...publicItems,
    ];

    if (userRole !== 'carrier') {
        items.push({
            href: route('vehicles.index'),
            label: 'Перевозчики',
            icon: Truck,
            active: route().current('vehicles.index'),
        });
    }

    if (userRole === 'shipper' || (userRole === 'carrier' && !isCarrierCompanyDriver)) {
        items.push({
            href: route('freight.company.edit'),
            label: 'Компания',
            icon: Building,
            active: route().current('freight.company.*'),
        });
    }

    if (userRole === 'shipper') {
        items.push(
            {
                href: route('loads.mine'),
                label: 'Мои заказы',
                icon: ClipboardList,
                active: route().current('loads.mine'),
            },
            {
                href: route('loads.create'),
                label: 'Создать груз',
                icon: Package,
                active: route().current('loads.create'),
            },
        );
    }

    if (userRole === 'carrier') {
        items.push({
            href: route('carrier.deliveries.index'),
            label: 'Мои перевозки',
            icon: ClipboardList,
            active: route().current('carrier.deliveries.*'),
        });

        if (!isCarrierCompanyDriver) {
            items.push({
                href: route('bids.mine'),
                label: 'Мои отклики',
                icon: MessageCircle,
                active: route().current('bids.mine'),
            });
        }

        items.push(
            {
                href: route('vehicles.mine'),
                label: isCarrierCompanyDriver ? 'Назначенный транспорт' : 'Мой транспорт',
                icon: Truck,
                active: route().current('vehicles.mine'),
            },
            {
                href: route('carrier.location'),
                label: 'Геолокация',
                icon: Map,
                active: route().current('carrier.location'),
            },
        );
    }

    items.push(
        {
            href: route('notifications.index'),
            label: 'Уведомления',
            icon: Bell,
            active: route().current('notifications.index'),
            badge: unreadNotificationsCount,
        },
        {
            href: route('complaints.index'),
            label: 'Жалобы',
            icon: MessageCircle,
            active: route().current('complaints.index'),
        },
    );

    if (userRole === 'dispatcher' || userRole === 'admin') {
        items.push({
            href: route('dispatcher.index'),
            label: 'Диспетчер',
            icon: Users,
            active: route().current('dispatcher.*'),
        });
    }

    if (userRole === 'admin') {
        items.push({
            href: route('admin.freight.index'),
            label: 'Админка',
            icon: Building,
            active: route().current('admin.freight.*'),
        });
    }

    const userData = {
        name: user.name,
        email: user.email,
        avatar: user.profile_photo_url || '',
    };

    return (
        <>
            <Sidebar collapsible="icon" {...props}>
                <SidebarHeader>
                    <div className="flex h-10 items-center gap-2 px-2 text-sm font-semibold">
                        <Truck className="size-4" />
                        <span>Биржа грузов</span>
                    </div>
                </SidebarHeader>
                <SidebarContent>
                    <SidebarGroup>
                        {items.map((item) => (
                            <SidebarMenuButton
                                key={`${item.href}-${item.label}`}
                                asChild
                                isActive={item.active}
                                tooltip={item.label}
                            >
                                <Link href={item.href} prefetch>
                                    <item.icon />
                                    <span>{item.label}</span>
                                    {item.badge ? (
                                        <span className="ml-auto min-w-5 rounded-full bg-primary px-1.5 text-center text-xs font-medium text-primary-foreground">
                                            {item.badge > 99 ? '99+' : item.badge}
                                        </span>
                                    ) : null}
                                </Link>
                            </SidebarMenuButton>
                        ))}
                        <SidebarMenuButton onClick={() => setIsFeedbackModalOpen(true)}>
                            <MessageCircle />
                            <span>Обратная связь</span>
                        </SidebarMenuButton>
                    </SidebarGroup>
                </SidebarContent>
                <SidebarFooter>
                    <NavUser user={userData} />
                </SidebarFooter>
                <SidebarRail />
            </Sidebar>

            <GeneralFeedbackModal
                isOpen={isFeedbackModalOpen}
                onClose={() => setIsFeedbackModalOpen(false)}
                userEmail={user.email}
            />
        </>
    );
}
