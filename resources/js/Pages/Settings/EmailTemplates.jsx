import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import Field from '../../Components/Field';
import FlashMessages from '../../Components/FlashMessages';
import { useLink } from '../../Components/LinkContext';
import AppLayout from '../../Components/AppLayout';

const primary = 'rounded bg-slate-900 px-3 py-1.5 text-sm text-white disabled:opacity-60';
const LOCALES = [['en', 'English'], ['ms', 'Bahasa Malaysia']];

function TemplateCard({ templateKey, value, placeholders, editable, errors, onSave }) {
    const { t } = useTranslation();
    const [v, setV] = useState(value);
    const set = (locale, field, val) => setV((s) => ({ ...s, [locale]: { ...s[locale], [field]: val } }));
    const errorFor = (locale, field) => errors[`${templateKey}.${locale}.${field}`];

    return (
        <form onSubmit={(e) => { e.preventDefault(); onSave?.(templateKey, v); }} className="space-y-3 rounded-lg bg-white p-4 shadow-sm">
            <div>
                <h2 className="font-medium">{t(`settingsPage.templates.${templateKey}`)}</h2>
                <p className="text-xs text-slate-500">{t('settingsPage.placeholders')}: {placeholders.map((p) => <code key={p} className="ml-1">{`:${p}`}</code>)}</p>
            </div>
            <div className="grid gap-4 sm:grid-cols-2">
                {LOCALES.map(([locale, label]) => (
                    <div key={locale} className="space-y-2">
                        <h3 className="text-xs font-medium uppercase text-slate-500">{label}</h3>
                        {editable ? (
                            <>
                                <Field
                                    label={t('settingsPage.subject')}
                                    value={v[locale].subject}
                                    onChange={(e) => set(locale, 'subject', e.target.value)}
                                    error={errorFor(locale, 'subject')}
                                />
                                <div className="text-sm">
                                    <label htmlFor={`${templateKey}-${locale}-body`}>{t('settingsPage.body')}</label>
                                    <textarea
                                        id={`${templateKey}-${locale}-body`}
                                        rows={4}
                                        value={v[locale].body}
                                        onChange={(e) => set(locale, 'body', e.target.value)}
                                        className="mt-1 w-full rounded border px-3 py-2"
                                    />
                                    {errorFor(locale, 'body') && <p role="alert" className="mt-1 text-red-700">{errorFor(locale, 'body')}</p>}
                                </div>
                            </>
                        ) : (
                            <>
                                <div className="text-sm"><span className="text-slate-500">{t('settingsPage.subject')}: </span>{v[locale].subject}</div>
                                <p className="whitespace-pre-line text-sm">{v[locale].body}</p>
                            </>
                        )}
                    </div>
                ))}
            </div>
            {editable && <button className={primary}>{t('settingsPage.save')}</button>}
        </form>
    );
}

// Dumb page: onSaveEmailTemplate(key, {en, ms}) comes from the entry point, one form per template.
export default function EmailTemplates({ templates = {}, placeholders = {}, can = {}, flash = {}, errors = {}, onSaveEmailTemplate, onSignOut }) {
    const { t } = useTranslation();
    const Link = useLink();

    return (
        <AppLayout current="settings" onSignOut={onSignOut}>
            <div className="space-y-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-xl font-semibold">{t('settingsPage.emailTemplates')}</h1>
                    <Link href="/settings" className="text-sm underline">{t('settingsPage.back')}</Link>
                </div>
                <FlashMessages flash={flash} errors={errors} />

                <div className="space-y-4">
                    {Object.entries(templates).map(([key, value]) => (
                        <TemplateCard
                            key={key}
                            templateKey={key}
                            value={value}
                            placeholders={placeholders[key] ?? []}
                            editable={can.update}
                            errors={errors}
                            onSave={onSaveEmailTemplate}
                        />
                    ))}
                </div>
            </div>
        </AppLayout>
    );
}
