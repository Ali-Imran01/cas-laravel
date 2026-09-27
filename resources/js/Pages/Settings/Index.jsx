import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import AppLayout from '../../Components/AppLayout';
import Field from '../../Components/Field';
import FlashMessages from '../../Components/FlashMessages';

const primary = 'rounded bg-slate-900 px-3 py-1.5 text-sm text-white disabled:opacity-60';

// Which fields sit in each card, in the order the form shows them.
const GROUPS = [
    ['password', ['password_min_length', 'password_history', 'password_expiry_days']],
    ['mfa', ['mfa_pending_minutes', 'mfa_max_attempts', 'mfa_email_otp_minutes', 'recovery_codes']],
    ['lockout', ['max_attempts', 'lockout_minutes', 'login_throttle_per_minute']],
];

function Group({ title, fields, values, editable, errors, onChange }) {
    const { t } = useTranslation();

    return (
        <section className="space-y-3 rounded-lg bg-white p-4 shadow-sm">
            <h2 className="font-medium">{title}</h2>
            <div className="grid gap-3 sm:grid-cols-3">
                {fields.map((field) => (
                    editable ? (
                        <Field
                            key={field}
                            type="number"
                            label={t(`settingsPage.fields.${field}`)}
                            value={values[field]}
                            onChange={(e) => onChange(field, e.target.value)}
                            error={errors[field]}
                        />
                    ) : (
                        <div key={field} className="text-sm">
                            <div className="text-slate-500">{t(`settingsPage.fields.${field}`)}</div>
                            <div className="mt-1 font-medium">{values[field]}</div>
                        </div>
                    )
                ))}
            </div>
        </section>
    );
}

// Dumb page: onSavePolicies comes from the entry point and posts every field at once.
export default function Index({ policies = {}, can = {}, flash = {}, errors = {}, onSavePolicies, onSignOut }) {
    const { t } = useTranslation();
    const [values, setValues] = useState(policies);
    const set = (field, value) => setValues((v) => ({ ...v, [field]: value }));

    return (
        <AppLayout current="settings" onSignOut={onSignOut}>
            <div className="space-y-4">
                <h1 className="text-xl font-semibold">{t('nav.settings')}</h1>
                <FlashMessages flash={flash} errors={errors} />

                <form onSubmit={(e) => { e.preventDefault(); onSavePolicies?.(values); }} className="space-y-4">
                    {GROUPS.map(([key, fields]) => (
                        <Group
                            key={key}
                            title={t(`settingsPage.groups.${key}`)}
                            fields={fields}
                            values={values}
                            editable={can.update}
                            errors={errors}
                            onChange={set}
                        />
                    ))}
                    {can.update && <button className={primary}>{t('settingsPage.save')}</button>}
                </form>
            </div>
        </AppLayout>
    );
}
