import '../css/app.css';
import './i18n';
import { createInertiaApp, Link, router } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import { LinkProvider } from './Components/LinkContext';

const pages = import.meta.glob('./Pages/**/*.jsx', { eager: true });

const keepScroll = { preserveScroll: true };
const drop = (obj) => Object.fromEntries(Object.entries(obj).filter(([, v]) => v !== '' && v != null));

// Pages are dumb: every server call is bound here, never inside Pages. `props` is the page's own
// props, so per-record actions can build their URLs from the record id.
const callbacksFor = (name, props) => ({
    onSignOut: () => router.post('/logout'),
    onSubmit: {
        Login: (data) => router.post('/login', data),
        ForgotPassword: (data) => router.post('/forgot-password', data),
        ResetPassword: (data) => router.post('/reset-password', data),
        AcceptInvite: (data) => router.post('/accept-invite', data),
        ChangePassword: (data) => router.put('/password/change', data),
        'Users/Create': (data) => router.post('/users', data),
        'Users/Edit': (data) => router.put(`/users/${props.user?.id}`, data),
    }[name],
    onVerify: (data) => router.post('/login/mfa', data),
    onSendEmail: () => router.post('/login/mfa/email'),
    onEnable: (code) => router.post('/mfa', { code }),
    onDisable: (current_password) => router.delete('/mfa', { data: { current_password } }),
    onFilter: (filters) => router.get('/users', drop(filters), { preserveState: true, replace: true }),
    onBulk: (data) => router.post('/users/bulk', data, keepScroll),
    onLock: () => router.post(`/users/${props.user?.id}/lock`, {}, keepScroll),
    onUnlock: () => router.post(`/users/${props.user?.id}/unlock`, {}, keepScroll),
    onDeactivate: () => router.post(`/users/${props.user?.id}/deactivate`, {}, keepScroll),
    onReactivate: () => router.post(`/users/${props.user?.id}/reactivate`, {}, keepScroll),
    onInvite: () => router.post(`/users/${props.user?.id}/invite`, {}, keepScroll),
    onTransfer: (data) => router.post(`/users/${props.user?.id}/transfer`, data, keepScroll),
    onDelete: () => router.delete(`/users/${props.user?.id}`),
});

createInertiaApp({
    resolve: (name) => {
        const Page = pages[`./Pages/${name}.jsx`].default;
        return (props) => <Page {...callbacksFor(name, props)} {...props} />;
    },
    setup({ el, App, props }) {
        createRoot(el).render(
            <LinkProvider value={Link}>
                <App {...props} />
            </LinkProvider>,
        );
    },
});
