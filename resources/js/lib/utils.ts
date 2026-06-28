import { clsx, type ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

const dateFormatter = new Intl.DateTimeFormat('ru-RU', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
});

const dateTimeFormatter = new Intl.DateTimeFormat('ru-RU', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
});

function parseDateValue(value?: string | null) {
    if (!value) {
        return null;
    }

    const normalized = /^\d{4}-\d{2}-\d{2}$/.test(value)
        ? `${value}T00:00:00`
        : value;
    const date = new Date(normalized);

    return Number.isNaN(date.getTime()) ? null : date;
}

export function formatDate(value?: string | null, fallback = 'не указана') {
    const date = parseDateValue(value);

    return date ? dateFormatter.format(date) : fallback;
}

export function formatDateTime(value?: string | null, fallback = 'не указано') {
    const date = parseDateValue(value);

    return date ? dateTimeFormatter.format(date) : fallback;
}

export function formatDateRange(from?: string | null, to?: string | null, fallback = 'даты не указаны') {
    const dates = [formatDate(from, ''), formatDate(to, '')].filter(Boolean);

    return dates.length > 0 ? dates.join(' - ') : fallback;
}

// Better as a utility in a separate file like utils/currency.ts
export const getCurrencySymbol = (currencyCode: string): string => {
    const symbols: Record<string, string> = {
        usd: '$',
        eur: '€',
        gbp: '£',
        // Add more currencies as needed
    };
    return symbols[currencyCode.toLowerCase()] || currencyCode;
};

export const formatCurrency = (value: number, currencyCode: string) => {
    const symbol = getCurrencySymbol(currencyCode);
    return `${symbol}${value.toFixed(2)}`;
};
