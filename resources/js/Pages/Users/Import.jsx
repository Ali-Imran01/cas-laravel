import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import AppLayout from '../../Components/AppLayout';
import FlashMessages from '../../Components/FlashMessages';
import { useLink } from '../../Components/LinkContext';
import ImportStatus from '../../Components/ImportStatus';

// Dumb page: onUpload(file) comes from the entry point.
export default function Import({ imports = [], maxKb, maxRows, flash = {}, errors = {}, onUpload, onSignOut }) {
    const { t, i18n } = useTranslation();
    const Link = useLink();
    const [file, setFile] = useState(null);

    return (
        <AppLayout current="users" onSignOut={onSignOut}>
            <div className="max-w-2xl space-y-6">
                <Link href="/users" className="text-sm underline">{t('users.show.back')}</Link>
                <h1 className="text-xl font-semibold">{t('import.title')}</h1>
                <FlashMessages flash={flash} errors={errors} />

                <section className="space-y-2 rounded-lg bg-white p-4 text-sm shadow-sm">
                    <p>{t('import.intro', { rows: maxRows, kb: maxKb })}</p>
                    <p className="font-mono text-xs">staff_id, name, email, org_unit_code, position_title, role</p>
                    <p className="text-slate-600">{t('import.columns')}</p>
                    <a href="/users/import/template" className="underline">{t('import.template')}</a>
                </section>

                <form
                    onSubmit={(e) => { e.preventDefault(); if (file) onUpload?.(file); }}
                    className="space-y-3 rounded-lg bg-white p-4 shadow-sm"
                >
                    <label htmlFor="import-file" className="block text-sm">{t('import.file')}</label>
                    <input
                        id="import-file"
                        type="file"
                        accept=".csv,text/csv,text/plain"
                        onChange={(e) => setFile(e.target.files?.[0] ?? null)}
                        aria-invalid={errors.file ? 'true' : undefined}
                        aria-describedby={errors.file ? 'import-file-error' : undefined}
                        className="block w-full text-sm"
                    />
                    {errors.file && <p id="import-file-error" className="text-sm text-red-700">{errors.file}</p>}
                    <button className="rounded bg-slate-900 px-3 py-2 text-sm text-white disabled:opacity-60" disabled={!file}>{t('import.start')}</button>
                </form>

                {imports.length > 0 && (
                    <section className="rounded-lg bg-white p-4 shadow-sm">
                        <h2 className="mb-2 font-medium">{t('import.recent')}</h2>
                        <ul className="divide-y text-sm">
                            {imports.map((i) => (
                                <li key={i.id} className="flex flex-wrap items-center justify-between gap-2 py-2">
                                    <Link href={`/users/imports/${i.id}`} className="underline">{new Date(i.created_at).toLocaleString(i18n.language)}</Link>
                                    <span className="flex items-center gap-3">
                                        <span className="text-slate-500">{t('import.counts', { ok: i.success_rows, bad: i.failed_rows })}</span>
                                        <ImportStatus status={i.status} />
                                    </span>
                                </li>
                            ))}
                        </ul>
                    </section>
                )}
            </div>
        </AppLayout>
    );
}
