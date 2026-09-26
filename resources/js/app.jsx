import '../css/app.css';
import './i18n';
import { createInertiaApp, Link, router } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import { LinkProvider } from './Components/LinkContext';

const pages = import.meta.glob('./Pages/**/*.jsx', { eager: true });

// Pages are dumb: server-bound callbacks are injected here, never inside Pages.
const callbacks = {
    onSignOut: () => router.post('/logout'),
    onSubmit: (data) => router.post('/login', data),
    onVerify: (data) => router.post('/login/mfa', data),
    onSendEmail: () => router.post('/login/mfa/email'),
    onEnable: (code) => router.post('/mfa', { code }),
    onDisable: (current_password) => router.delete('/mfa', { data: { current_password } }),
};

// Screens whose onSubmit is not the sign-in form.
const submitFor = {
    ForgotPassword: (data) => router.post('/forgot-password', data),
    ResetPassword: (data) => router.post('/reset-password', data),
    ChangePassword: (data) => router.put('/password/change', data),
};

createInertiaApp({
    resolve: (name) => {
        const Page = pages[`./Pages/${name}.jsx`].default;
        const bound = { ...callbacks, ...(submitFor[name] && { onSubmit: submitFor[name] }) };
        return (props) => <Page {...bound} {...props} />;
    },
    setup({ el, App, props }) {
        createRoot(el).render(
            <LinkProvider value={Link}>
                <App {...props} />
            </LinkProvider>,
        );
    },
});
