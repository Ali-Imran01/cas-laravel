import '../css/app.css';
import './i18n';
import { createInertiaApp, Link, router } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import { AccessProvider } from './Components/AccessContext';
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
        'Approvals/New': (data) => router.post('/approvals', data),
    }[name],
    onVerify: (data) => router.post('/login/mfa', data),
    onSendEmail: () => router.post('/login/mfa/email'),
    onEnable: (code) => router.post('/mfa', { code }),
    onDisable: (current_password) => router.delete('/mfa', { data: { current_password } }),
    onFilter: ({ view, ...filters }) => router.get(name === 'Audit/Index' ? '/audit' : '/users', drop({ view, ...filters }), { preserveState: true, replace: true }),
    onBulk: (data) => router.post('/users/bulk', data, keepScroll),
    onLock: () => router.post(`/users/${props.user?.id}/lock`, {}, keepScroll),
    onUnlock: () => router.post(`/users/${props.user?.id}/unlock`, {}, keepScroll),
    onDeactivate: () => router.post(`/users/${props.user?.id}/deactivate`, {}, keepScroll),
    onReactivate: () => router.post(`/users/${props.user?.id}/reactivate`, {}, keepScroll),
    onInvite: () => router.post(`/users/${props.user?.id}/invite`, {}, keepScroll),
    onTransfer: (data) => router.post(`/users/${props.user?.id}/transfer`, data, keepScroll),
    onDelete: () => router.delete(`/users/${props.user?.id}`),
    onRegisterApp: (data) => router.post('/apps', data, keepScroll),
    onUpdateApp: (data) => router.put(`/apps/${props.selected?.id}`, data, keepScroll),
    onRotateSecret: () => router.post(`/apps/${props.selected?.id}/secret`, {}, keepScroll),
    onDisableApp: () => router.post(`/apps/${props.selected?.id}/disable`, {}, keepScroll),
    onEnableApp: () => router.post(`/apps/${props.selected?.id}/enable`, {}, keepScroll),
    onDeleteApp: () => router.delete(`/apps/${props.selected?.id}`),
    onMapRole: (data) => router.post(`/apps/${props.selected?.id}/roles`, data, keepScroll),
    onUnmapRole: (roleId) => router.delete(`/apps/${props.selected?.id}/roles/${roleId}`, keepScroll),
    onGrantUser: (data) => router.post(`/apps/${props.selected?.id}/users`, data, keepScroll),
    onRevokeUser: (userId) => router.delete(`/apps/${props.selected?.id}/users/${userId}`, keepScroll),
    onUpdateWebhook: (webhook_url) => router.put(`/apps/${props.selected?.id}/webhook`, { webhook_url }, keepScroll),
    onRotateWebhookSecret: () => router.post(`/apps/${props.selected?.id}/webhook/secret`, {}, keepScroll),
    onCreateRole: (data) => router.post('/roles', data, keepScroll),
    onUpdateRole: (data) => router.put(`/roles/${props.selected?.id}`, data, keepScroll),
    onSavePermissions: (permissions) => router.put(`/roles/${props.selected?.id}/permissions`, { permissions }, keepScroll),
    onDeleteRole: () => router.delete(`/roles/${props.selected?.id}`),
    onUpload: (file) => router.post('/users/import', { file }, { forceFormData: true }),
    onRefresh: () => router.reload({ only: ['import'] }),
    onCreateUnit: (data) => router.post('/organization/units', data, keepScroll),
    onUpdateUnit: (data) => router.put(`/organization/units/${props.selected?.id}`, data, keepScroll),
    onMoveUnit: (parent_id) => router.post(`/organization/units/${props.selected?.id}/move`, { parent_id }, keepScroll),
    onDeleteUnit: () => router.delete(`/organization/units/${props.selected?.id}`),
    onSavePosition: ({ id, ...data }) => (id
        ? router.put(`/organization/positions/${id}`, data, keepScroll)
        : router.post(`/organization/units/${props.selected?.id}/positions`, data, keepScroll)),
    onDeletePosition: (id) => router.delete(`/organization/positions/${id}`, keepScroll),
    onApprove: () => router.post(`/approvals/${props.selected?.id}/approve`, {}, keepScroll),
    onReject: (comment) => router.post(`/approvals/${props.selected?.id}/reject`, { comment }, keepScroll),
    onRequestInfo: (comment) => router.post(`/approvals/${props.selected?.id}/request-info`, { comment }, keepScroll),
    onResubmit: (comment) => router.post(`/approvals/${props.selected?.id}/resubmit`, { comment }, keepScroll),
    onComment: (comment) => router.post(`/approvals/${props.selected?.id}/comment`, { comment }, keepScroll),
    onCancel: () => router.post(`/approvals/${props.selected?.id}/cancel`, {}, keepScroll),
    onUpdateWorkflow: (id, data) => router.put(`/approvals/workflows/${id}`, data, keepScroll),
    onUpdateStep: (workflowId, stepId, data) => router.put(`/approvals/workflows/${workflowId}/steps/${stepId}`, data, keepScroll),
    onSavePolicies: (data) => router.put('/settings/policies', data, keepScroll),
});

createInertiaApp({
    resolve: (name) => {
        const Page = pages[`./Pages/${name}.jsx`].default;
        return (props) => {
            const held = new Set(props.auth?.permissions ?? []);
            return (
                <AccessProvider value={(permission) => held.has(permission)}>
                    <Page {...callbacksFor(name, props)} {...props} />
                </AccessProvider>
            );
        };
    },
    setup({ el, App, props }) {
        createRoot(el).render(
            <LinkProvider value={Link}>
                <App {...props} />
            </LinkProvider>,
        );
    },
});
