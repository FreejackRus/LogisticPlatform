import '../css/app.css';
import './bootstrap';

import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';

import { ThemeProvider } from '@/Components/ui/theme-provider';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';
const pages = {
    './Pages/Dashboard.tsx': () => import('./Pages/Dashboard'),
    ...import.meta.glob('./Pages/Auth/**/*.tsx'),
    ...import.meta.glob('./Pages/Freight/**/*.tsx'),
    ...import.meta.glob('./Pages/Profile/**/*.tsx'),
};

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.tsx`,
            pages,
        ),
    setup({ el, App, props }) {
        const root = createRoot(el);

        root.render(
            <ThemeProvider defaultTheme="system" storageKey="ui-theme">
                <App {...props} />
            </ThemeProvider>,
        );
    },
    progress: {
        color: '#4B5563',
    },
});
