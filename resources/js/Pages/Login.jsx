import { useState } from 'react';
import { useTranslation } from 'react-i18next';

// Dumb page: onSubmit({identifier, password}) and onVerifyMfa(code) come from the entry point.
export default function Login({ mfaRequired = false, onSubmit, onVerifyMfa }) {
    const { t } = useTranslation();
    const [identifier, setIdentifier] = useState('');
    const [password, setPassword] = useState('');
    const [code, setCode] = useState('');

    const submit = (e) => {
        e.preventDefault();
        if (mfaRequired) onVerifyMfa?.(code);
        else onSubmit?.({ identifier, password });
    };

    const field = 'mt-1 w-full rounded border px-3 py-2';

    return (
        <div className="flex min-h-screen items-center justify-center">
            <form onSubmit={submit} className="w-full max-w-sm space-y-4 rounded-lg bg-white p-8 shadow">
                <h1 className="text-xl font-semibold">{mfaRequired ? t('login.mfaTitle') : t('login.title')}</h1>
                {mfaRequired ? (
                    <label className="block text-sm">
                        {t('login.mfaCode')}
                        <input value={code} onChange={(e) => setCode(e.target.value)} inputMode="numeric" maxLength={6} className={field} />
                    </label>
                ) : (
                    <>
                        <label className="block text-sm">
                            {t('login.identifier')}
                            <input value={identifier} onChange={(e) => setIdentifier(e.target.value)} className={field} />
                        </label>
                        <label className="block text-sm">
                            {t('login.password')}
                            <input type="password" value={password} onChange={(e) => setPassword(e.target.value)} className={field} />
                        </label>
                    </>
                )}
                <button className="w-full rounded bg-slate-900 py-2 text-white">
                    {mfaRequired ? t('login.mfaSubmit') : t('login.submit')}
                </button>
            </form>
        </div>
    );
}
