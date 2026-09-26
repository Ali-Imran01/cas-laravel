import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import AuthCard from '../Components/AuthCard';
import Field, { PrimaryButton } from '../Components/Field';
import { useLink } from '../Components/LinkContext';

const METHODS = ['totp', 'email', 'recovery'];

// Dumb page: onVerify({method, code}) and onSendEmail() come from the entry point.
export default function MfaChallenge({ errors = {}, flash = {}, onVerify, onSendEmail }) {
    const { t } = useTranslation();
    const Link = useLink();
    const [method, setMethod] = useState('totp');
    const [code, setCode] = useState('');

    const submit = (e) => {
        e.preventDefault();
        onVerify?.({ method, code });
    };

    return (
        <AuthCard title={t('mfa.title')} status={flash.status} onSubmit={submit}>
            <div role="tablist" className="flex gap-1 text-xs">
                {METHODS.map((m) => (
                    <button
                        key={m}
                        type="button"
                        role="tab"
                        aria-selected={method === m}
                        onClick={() => setMethod(m)}
                        className={`flex-1 rounded border px-2 py-1 ${method === m ? 'bg-slate-900 text-white' : ''}`}
                    >
                        {t(`mfa.${m}`)}
                    </button>
                ))}
            </div>
            <Field
                label={t('mfa.code')}
                value={code}
                onChange={(e) => setCode(e.target.value)}
                error={errors.code}
                inputMode={method === 'recovery' ? 'text' : 'numeric'}
                autoComplete="one-time-code"
                autoFocus
            />
            {method === 'email' && (
                <button type="button" onClick={onSendEmail} className="text-sm underline">{t('mfa.sendEmail')}</button>
            )}
            <PrimaryButton>{t('mfa.submit')}</PrimaryButton>
            <Link href="/login" className="block text-center text-sm underline">{t('mfa.back')}</Link>
        </AuthCard>
    );
}
