import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import AuthCard from '../Components/AuthCard';
import Field, { PrimaryButton } from '../Components/Field';
import { useLink } from '../Components/LinkContext';

// Dumb page: onSubmit({email}) comes from the entry point.
export default function ForgotPassword({ errors = {}, flash = {}, onSubmit }) {
    const { t } = useTranslation();
    const Link = useLink();
    const [email, setEmail] = useState('');

    return (
        <AuthCard title={t('forgot.title')} status={flash.status} onSubmit={(e) => { e.preventDefault(); onSubmit?.({ email }); }}>
            <Field label={t('forgot.email')} type="email" value={email} onChange={(e) => setEmail(e.target.value)} error={errors.email} autoComplete="email" autoFocus />
            <PrimaryButton>{t('forgot.submit')}</PrimaryButton>
            <Link href="/login" className="block text-center text-sm underline">{t('forgot.back')}</Link>
        </AuthCard>
    );
}
