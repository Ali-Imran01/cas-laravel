import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import AppLayout from '../Components/AppLayout';
import Field, { PrimaryButton } from '../Components/Field';

// Dumb page: onSubmit({current_password, password, password_confirmation}) comes from the entry point.
export default function ChangePassword({ forced = false, errors = {}, onSubmit, onSignOut }) {
    const { t } = useTranslation();
    const [current, setCurrent] = useState('');
    const [password, setPassword] = useState('');
    const [confirmation, setConfirmation] = useState('');

    const submit = (e) => {
        e.preventDefault();
        onSubmit?.({ current_password: current, password, password_confirmation: confirmation });
    };

    return (
        <AppLayout current="settings" onSignOut={onSignOut}>
            <form onSubmit={submit} className="max-w-md space-y-4">
                <h1 className="text-xl font-semibold">{t('password.title')}</h1>
                {forced && <p role="alert" className="rounded bg-amber-50 p-2 text-sm text-amber-900">{t('password.forced')}</p>}
                <Field label={t('password.current')} type="password" value={current} onChange={(e) => setCurrent(e.target.value)} error={errors.current_password} autoComplete="current-password" />
                <Field label={t('password.new')} type="password" value={password} onChange={(e) => setPassword(e.target.value)} error={errors.password} autoComplete="new-password" />
                <Field label={t('password.confirm')} type="password" value={confirmation} onChange={(e) => setConfirmation(e.target.value)} autoComplete="new-password" />
                <p className="text-xs text-slate-500">{t('password.policy')}</p>
                <PrimaryButton>{t('password.submit')}</PrimaryButton>
            </form>
        </AppLayout>
    );
}
