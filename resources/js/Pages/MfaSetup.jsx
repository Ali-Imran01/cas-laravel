import { useState } from 'react';
import { QRCodeSVG } from 'qrcode.react';
import { useTranslation } from 'react-i18next';
import AppLayout from '../Components/AppLayout';
import Field, { PrimaryButton } from '../Components/Field';

// Dumb page: onEnable(code) and onDisable(password) come from the entry point.
export default function MfaSetup({ enabled, secret, uri, errors = {}, flash = {}, onEnable, onDisable, onSignOut }) {
    const { t } = useTranslation();
    const [code, setCode] = useState('');
    const [password, setPassword] = useState('');

    return (
        <AppLayout current="settings" onSignOut={onSignOut}>
            <div className="max-w-md space-y-6">
                <h1 className="text-xl font-semibold">{t('mfaSetup.title')}</h1>
                {flash.status && <p role="status" className="rounded bg-emerald-50 p-2 text-sm text-emerald-800">{t(`flash.${flash.status}`, { defaultValue: flash.status })}</p>}

                {flash.recoveryCodes && (
                    <section className="rounded border p-4">
                        <h2 className="font-medium">{t('mfaSetup.recoveryTitle')}</h2>
                        <p className="mb-2 text-sm text-slate-600">{t('mfaSetup.recoveryHint')}</p>
                        <ul className="grid grid-cols-2 gap-1 font-mono text-sm">
                            {flash.recoveryCodes.map((c) => <li key={c}>{c}</li>)}
                        </ul>
                    </section>
                )}

                {enabled ? (
                    <form onSubmit={(e) => { e.preventDefault(); onDisable?.(password); }} className="space-y-4">
                        <p>{t('mfaSetup.on')}</p>
                        <Field label={t('mfaSetup.currentPassword')} type="password" value={password} onChange={(e) => setPassword(e.target.value)} error={errors.current_password} autoComplete="current-password" />
                        <PrimaryButton>{t('mfaSetup.disable')}</PrimaryButton>
                    </form>
                ) : (
                    <form onSubmit={(e) => { e.preventDefault(); onEnable?.(code); }} className="space-y-4">
                        <p className="text-sm">{t('mfaSetup.intro')}</p>
                        {uri && <QRCodeSVG value={uri} size={168} title={t('mfaSetup.title')} />}
                        <p className="text-xs text-slate-600">{t('mfaSetup.manualKey')}: <code className="break-all">{secret}</code></p>
                        <Field label={t('mfaSetup.code')} value={code} onChange={(e) => setCode(e.target.value)} error={errors.code} inputMode="numeric" autoComplete="one-time-code" />
                        <PrimaryButton>{t('mfaSetup.enable')}</PrimaryButton>
                    </form>
                )}
            </div>
        </AppLayout>
    );
}
