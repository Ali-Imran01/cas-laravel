import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import AuthCard from '../Components/AuthCard';
import Field, { PrimaryButton } from '../Components/Field';
import { useLink } from '../Components/LinkContext';

// Dumb page: onSubmit({identifier, password, remember}) comes from the entry point.
export default function Login({ errors = {}, flash = {}, onSubmit }) {
    const { t } = useTranslation();
    const Link = useLink();
    const [identifier, setIdentifier] = useState('');
    const [password, setPassword] = useState('');
    const [remember, setRemember] = useState(false);

    const submit = (e) => {
        e.preventDefault();
        onSubmit?.({ identifier, password, remember });
    };

    return (
        <AuthCard title={t('login.title')} status={flash.status} onSubmit={submit}>
            <Field label={t('login.identifier')} value={identifier} onChange={(e) => setIdentifier(e.target.value)} error={errors.identifier} autoComplete="username" autoFocus />
            <Field label={t('login.password')} type="password" value={password} onChange={(e) => setPassword(e.target.value)} error={errors.password} autoComplete="current-password" />
            <div className="flex items-center justify-between text-sm">
                <label className="flex items-center gap-2">
                    <input type="checkbox" checked={remember} onChange={(e) => setRemember(e.target.checked)} />
                    {t('login.remember')}
                </label>
                <Link href="/forgot-password" className="underline">{t('login.forgot')}</Link>
            </div>
            <PrimaryButton>{t('login.submit')}</PrimaryButton>
        </AuthCard>
    );
}
