import { Input } from '@/Components/ui/input';
import axios from 'axios';
import { useEffect, useRef, useState } from 'react';

export type AddressSuggestion = {
    title: string;
    subtitle?: string | null;
    city?: string | null;
    address?: string | null;
    lat?: number | null;
    lng?: number | null;
};

type Props = {
    id: string;
    value: string;
    required?: boolean;
    placeholder?: string;
    valueFromSuggestion?: (suggestion: AddressSuggestion) => string;
    onChange: (value: string) => void;
    onSelect?: (suggestion: AddressSuggestion) => void;
};

export function AddressSuggestInput({ id, value, required, placeholder, valueFromSuggestion, onChange, onSelect }: Props) {
    const [suggestions, setSuggestions] = useState<AddressSuggestion[]>([]);
    const [isOpen, setIsOpen] = useState(false);
    const [isLoading, setIsLoading] = useState(false);
    const requestId = useRef(0);

    useEffect(() => {
        const query = value.trim();

        if (query.length < 2) {
            setSuggestions([]);
            setIsOpen(false);
            return;
        }

        const currentRequestId = requestId.current + 1;
        requestId.current = currentRequestId;

        const timer = window.setTimeout(() => {
            setIsLoading(true);
            axios
                .get(route('api.geocoder.suggest'), { params: { q: query, limit: 6 } })
                .then(({ data }) => {
                    if (requestId.current !== currentRequestId) {
                        return;
                    }

                    setSuggestions(data.suggestions ?? []);
                    setIsOpen((data.suggestions ?? []).length > 0);
                })
                .catch(() => {
                    if (requestId.current === currentRequestId) {
                        setSuggestions([]);
                        setIsOpen(false);
                    }
                })
                .finally(() => {
                    if (requestId.current === currentRequestId) {
                        setIsLoading(false);
                    }
                });
        }, 250);

        return () => window.clearTimeout(timer);
    }, [value]);

    const selectSuggestion = (suggestion: AddressSuggestion) => {
        onChange(valueFromSuggestion ? valueFromSuggestion(suggestion) : suggestion.city || suggestion.title);
        onSelect?.(suggestion);
        setIsOpen(false);
    };

    return (
        <div className="relative">
            <Input
                id={id}
                type="text"
                value={value}
                placeholder={placeholder}
                required={required}
                autoComplete="off"
                onChange={(event) => onChange(event.target.value)}
                onFocus={() => setIsOpen(suggestions.length > 0)}
                onBlur={() => window.setTimeout(() => setIsOpen(false), 150)}
            />
            {isOpen && (
                <div className="absolute z-30 mt-1 max-h-64 w-full overflow-auto rounded-md border bg-popover text-popover-foreground shadow-md">
                    {suggestions.map((suggestion, index) => (
                        <button
                            key={`${suggestion.title}-${suggestion.subtitle ?? ''}-${index}`}
                            type="button"
                            className="grid w-full gap-0.5 px-3 py-2 text-left text-sm hover:bg-muted focus:bg-muted focus:outline-none"
                            onMouseDown={(event) => event.preventDefault()}
                            onClick={() => selectSuggestion(suggestion)}
                        >
                            <span>{suggestion.title}</span>
                            {suggestion.subtitle && <span className="text-xs text-muted-foreground">{suggestion.subtitle}</span>}
                        </button>
                    ))}
                </div>
            )}
            {isLoading && <p className="mt-1 text-xs text-muted-foreground">Ищем адрес...</p>}
        </div>
    );
}
