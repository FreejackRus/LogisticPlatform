export interface User {
    id: number;
    name: string;
    email: string;
    phone?: string | null;
    role?: 'admin' | 'shipper' | 'carrier' | 'dispatcher';
    is_active?: boolean;
    is_blocked?: boolean;
    email_verified_at?: string | null;
    profile_photo_url?: string | null;
    timezone?: string | null;
    language_preference?: string | null;
    carrier_member_role?: 'driver' | 'manager' | null;
    can_manage_carrier_fleet?: boolean;
    is_carrier_company_driver?: boolean;
    active_carrier_company?: {
        id: number;
        name: string;
        carrier_profile_type?: 'individual' | 'company' | null;
    } | null;
}

export interface Company {
    id: number;
    user_id: number;
    type: 'shipper' | 'carrier';
    name: string;
    short_name?: string | null;
    inn?: string | null;
    phone?: string | null;
    email?: string | null;
    carrier_profile_type?: 'individual' | 'company' | null;
    verification_status?: 'not_verified' | 'pending' | 'verified' | 'rejected';
    rating?: number | string | null;
    reviews_count?: number | null;
}

export interface FreightLoad {
    id: number;
    title: string;
    status: 'draft' | 'active' | 'in_progress' | 'completed' | 'cancelled' | 'archived';
    delivery_stage?: string | null;
    loading_city: string;
    unloading_city: string;
    loading_date?: string | null;
    unloading_date?: string | null;
    price?: number | null;
    price_currency?: string | null;
    body_type?: string | null;
    company?: Company | null;
}

export interface Vehicle {
    id: number;
    title: string;
    vehicle_type?: string | null;
    body_type?: string | null;
    registration_number?: string | null;
    current_city?: string | null;
    is_available?: boolean;
    is_online?: boolean;
    company?: Company | null;
}

export interface Bid {
    id: number;
    status: 'pending' | 'accepted' | 'rejected' | 'cancelled';
    freight_load?: FreightLoad;
    vehicle?: Vehicle | null;
    carrier?: User | null;
    company?: Company | null;
}

export interface FreightNotification {
    id: number;
    type: string;
    title: string;
    message?: string | null;
    is_read: boolean;
    created_at?: string | null;
}

export interface DeliveryEvent {
    id: number;
    type: string;
    note?: string | null;
    created_at?: string | null;
    actor?: User | null;
}

export interface Timezone {
    id: number;
    name: string;
}

export interface TimezoneData {
    identifier: string;
    dst_tz: string;
    std_tz: string;
}

export type PageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    auth: {
        user: User | null;
        permissions: Record<string, boolean>;
    };
    app: {
        name: string;
    };
    integration_settings: Record<string, string>;
};
