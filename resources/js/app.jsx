import '../css/app.css';
import './i18n';
import { createInertiaApp, Link, router } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import { LinkProvider } from './Components/LinkContext';

const pages = import.meta.glob('./Pages/**/*.jsx', { eager: true });

// Pages are dumb: server-bound callbacks are injected here, never inside Pages.
const callbacks = {
    onSignOut: () => router.post('/logout'),
    onSubmit: (credentials) => router.post('/login', credentials),
    onVerifyMfa: (code) => router.post('/login/mfa', { code }),
};

createInertiaApp({
    resolve: (name) => {
        const Page = pages[`./Pages/${name}.jsx`].default;
        return (props) => <Page {...callbacks} {...props} />;
    },
    setup({ el, App, props }) {
        createRoot(el).render(
            <LinkProvider value={Link}>
                <App {...props} />
            </LinkProvider>,
        );
    },
});
