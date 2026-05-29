import './bootstrap';
import '../css/app.css';

import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';

createInertiaApp({
    id: 'app',
    title: (title) => (title ? `${title} — Finance` : 'Finance'),
    resolve: (name) => {
        const pages = import.meta.glob('./Pages/**/*.jsx');
        const loader = pages[`./Pages/${name}.jsx`];
        if (!loader) {
            throw new Error(`Unknown Inertia page: ${name}`);
        }
        return loader();
    },
    setup({ el, App, props }) {
        if (!el) {
            throw new Error('Inertia root element #app not found.');
        }
        createRoot(el).render(<App {...props} />);
    },
    progress: {
        color: '#119a48',
        showSpinner: true,
    },
});
