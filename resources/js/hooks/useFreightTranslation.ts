import { PageProps as InertiaPageProps } from '@inertiajs/core';
import { usePage } from '@inertiajs/react';
import { useCallback } from 'react';

type TranslationValue = string | TranslationTree;
type TranslationTree = {
    [key: string]: TranslationValue;
};

interface PageProps extends InertiaPageProps {
    translations?: {
        freight?: TranslationTree;
    };
}

function pick(tree: TranslationTree | undefined, key: string): string | undefined {
    const value = key.split('.').reduce<TranslationValue | undefined>((current, part) => {
        if (!current || typeof current === 'string') return undefined;

        return current[part];
    }, tree);

    return typeof value === 'string' ? value : undefined;
}

function choosePlural(template: string, replacements?: Record<string, string | number>) {
    if (!template.includes('|') || replacements?.count === undefined) {
        return template;
    }

    const variants = template.split('|');
    const count = Number(replacements.count);
    const abs = Math.abs(count);

    if (variants.length === 2) {
        return abs === 1 ? variants[0] : variants[1];
    }

    const mod10 = abs % 10;
    const mod100 = abs % 100;

    if (mod10 === 1 && mod100 !== 11) return variants[0];
    if (mod10 >= 2 && mod10 <= 4 && (mod100 < 12 || mod100 > 14)) return variants[1];

    return variants[2] ?? variants[variants.length - 1];
}

export function useFreightTranslation() {
    const freight = usePage<PageProps>().props.translations?.freight;

    return useCallback((key: string, replacements: Record<string, string | number> = {}) => {
        const template = choosePlural(pick(freight, key) ?? key, replacements);

        return Object.entries(replacements).reduce(
            (text, [name, value]) => text.replaceAll(`{${name}}`, String(value)),
            template,
        );
    }, [freight]);
}
