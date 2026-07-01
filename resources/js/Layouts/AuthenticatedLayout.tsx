import { AppSidebar } from '@/Components/AppSidebar';
import {
    Breadcrumb,
    BreadcrumbItem,
    BreadcrumbLink,
    BreadcrumbList,
    BreadcrumbPage,
    BreadcrumbSeparator,
} from '@/Components/ui/breadcrumb';
import { Separator } from '@/Components/ui/separator';
import {
    SidebarInset,
    SidebarProvider,
    SidebarTrigger,
} from '@/Components/ui/sidebar';
import { Toaster } from '@/Components/ui/toaster';
import { useInertiaErrorHandler } from '@/hooks/useInertiaErrorHandler';
import { Link, usePage } from '@inertiajs/react';
import { AlertTriangle } from 'lucide-react';
import { Fragment, PropsWithChildren } from 'react';

interface BreadcrumbItem {
    title: string;
    href?: string;
}

export default function Authenticated({
    children,
    breadcrumbs = [],
}: PropsWithChildren<{ breadcrumbs?: BreadcrumbItem[] }>) {
    useInertiaErrorHandler();
    const { auth } = usePage().props as any;
    const user = auth?.user;
    const companyStatus = user?.business_company?.verification_status;
    const isCompanyBlocked = Boolean(user?.business_company?.is_blocked);
    const shouldShowBusinessAlert = user
        && ['shipper', 'carrier'].includes(user.role)
        && (!user.has_verified_business_profile || isCompanyBlocked);
    const canEditCompany = user?.role === 'shipper' || (user?.role === 'carrier' && !user?.is_carrier_company_driver);
    const businessAlertText = isCompanyBlocked
        ? 'Профиль компании заблокирован. Сделки и публикации недоступны до решения модерации.'
        : companyStatus === 'pending'
            ? 'Профиль компании находится на проверке. Публикация грузов и отклики станут доступны после подтверждения.'
            : companyStatus === 'rejected'
                ? 'Профиль компании отклонён. Исправьте данные и отправьте их на повторную проверку.'
                : 'Заполните профиль компании, чтобы публиковать грузы и откликаться на заказы.';

    const savedSidebarState = document.cookie
        .split('; ')
        .find((row) => row.startsWith('sidebar:state'))
        ?.split('=')[1];
    const sideBarOpen = savedSidebarState ? savedSidebarState === 'true' : true;

    return (
        <SidebarProvider defaultOpen={sideBarOpen}>
            <AppSidebar />
            <SidebarInset>
                <header className="flex h-16 shrink-0 items-center gap-2 transition-[width,height] ease-linear group-has-[[data-collapsible=icon]]/sidebar-wrapper:h-12">
                    <div className="flex items-center gap-2 px-4">
                        <SidebarTrigger className="-ml-1" />
                        <Separator
                            orientation="vertical"
                            className="mr-2 h-4"
                        />
                        <Breadcrumb>
                            <BreadcrumbList>
                                {breadcrumbs.map((item, index) => (
                                    <Fragment key={index}>
                                        <BreadcrumbItem className="hidden md:block">
                                            {item.href ? (
                                                <BreadcrumbLink asChild>
                                                    <Link
                                                        prefetch={true}
                                                        href={item.href}
                                                    >
                                                        {item.title}
                                                    </Link>
                                                </BreadcrumbLink>
                                            ) : (
                                                <BreadcrumbPage>
                                                    {item.title}
                                                </BreadcrumbPage>
                                            )}
                                        </BreadcrumbItem>
                                        {index < breadcrumbs.length - 1 && (
                                            <BreadcrumbSeparator className="hidden md:block" />
                                        )}
                                    </Fragment>
                                ))}
                            </BreadcrumbList>
                        </Breadcrumb>
                    </div>
                </header>
                {shouldShowBusinessAlert && (
                    <div className="mx-3 mb-2 rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950">
                        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div className="flex gap-2">
                                <AlertTriangle className="mt-0.5 size-4 shrink-0" />
                                <span>{businessAlertText}</span>
                            </div>
                            {canEditCompany && (
                                <Link className="shrink-0 font-medium underline" href={route('freight.company.edit')}>
                                    Открыть профиль
                                </Link>
                            )}
                        </div>
                    </div>
                )}
                <main className="px-1 pb-2">{children}</main>
                <Toaster />
            </SidebarInset>
        </SidebarProvider>
    );
}
