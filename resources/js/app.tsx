import { createInertiaApp, router } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import { ComponentType } from 'react';

createInertiaApp({
    title: (title) => (title ? `${title} · Tanwiriyyah` : 'Tanwiriyyah'),
    resolve: (name) => {
        const pages = import.meta.glob('./Pages/**/*.tsx', { eager: true });
        const page = pages[`./Pages/${name}.tsx`] as { default: ComponentType } | undefined;

        if (!page) {
            throw new Error(`Halaman ${name} tidak ditemukan.`);
        }

        return page;
    },
    setup({ el, App, props }) {
        if (!el) {
            return;
        }

        createRoot(el).render(<App {...props} />);
    },
    progress: false,
});

const bar = document.createElement('div');
bar.className = 'progress';
bar.style.width = '0';
document.addEventListener('DOMContentLoaded', () => document.body.appendChild(bar));

router.on('start', () => {
    bar.style.width = '35%';
});
router.on('finish', () => {
    bar.style.width = '100%';
    window.setTimeout(() => {
        bar.style.width = '0';
    }, 180);
});
