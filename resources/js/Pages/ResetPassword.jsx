import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import AuthCard from '../Components/AuthCard';
import Field, { PrimaryButton } from '../Components/Field';

// Dumb page: onSubmit({token, email, password, password_confirmation}) comes from the entry point.
export default function ResetPassword({ token, email: initialEmail = '', errors = {}, onSubmit }) {
    const { t } = useTranslation();
    const [email, setEmail] = useState(initialEmail);
    const [password, setPassword] = useState('');
    const [confirmation, setConfirmation] = useState('');

    const submit = (e) => {
        e.preventDefault();
        onSubmit?.({ token, email, password, password_confirmation: confirmation });
    };

    return (
        <AuthCard title={t('reset.title')} onSubmit={submit}>
            <Field label={t('reset.email')} type="email" value={email} onChange={(e) => setEmail(e.target.value)} error={errors.email} autoComplete="email" />
            <Field label={t('reset.password')} type="password" value={password} onChange={(e) => setPassword(e.target.value)} error={errors.password} autoComplete="new-password" autoFocus />
            <Field label={t('reset.confirm')} type="password" value={confirmation} onChange={(e) => setConfirmation(e.target.value)} autoComplete="new-password" />
            <p className="text-xs text-slate-500">{t('password.policy')}</p>
            <PrimaryButton>{t('reset.submit')}</PrimaryButton>
        </AuthCard>
    );
}
