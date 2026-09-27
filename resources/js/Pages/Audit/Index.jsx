import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import AppLayout from '../../Components/AppLayout';
import { useLink } from '../../Components/LinkContext';
import SelectField from '../../Components/SelectField';

const RESULT_TONE = { success: 'bg-emerald-100 text-emerald-800', failed: 'bg-red-100 text-red-800', blocked: 'bg-amber-100 text-amber-900' };

const Values = ({ label, values }) => (values ? (
    <div>
        <div className="text-xs uppercase text-slate-500">{label}</div>
        <pre className="overflow-x-auto rounded bg-slate-50 p-2 text-xs">{JSON.stringify(values, null, 2)}</pre>
    </div>
) : null);

// Dumb page: onFilter(filters) comes from the entry point. The tabs, pagination and export are plain links.
export default function Index({ view = 'log', entries, filters = {}, actions = [], subjects = [], results = [], methods = [], exportQuery = '', onFilter, onSignOut }) {
    const { t, i18n } = useTranslation();
    const Link = useLink();
    const [form, setForm] = useState({ search: filters.search ?? '', action: filters.action ?? '', actor: filters.actor ?? '', subject: filters.subject ?? '', result: filters.result ?? '', method: filters.method ?? '', from: filters.from ?? '', to: filters.to ?? '' });
    const [open, setOpen] = useState(null);
    const set = (key) => (e) => setForm((f) => ({ ...f, [key]: e.target.value }));
    const signIns = view === 'signins';
    const when = (iso) => new Date(iso).toLocaleString(i18n.language);
    const opt = (list, prefix) => list.map((v) => ({ value: v, label: prefix ? t(`${prefix}.${v}`, { defaultValue: v }) : v }));

    const submit = (e) => {
        e.preventDefault();
        onFilter?.({ ...form, view });
    };
    const tab = (key, label) => (
        <Link
            href={`/audit${key === 'signins' ? '?view=signins' : ''}`}
            aria-current={view === key ? 'page' : undefined}
            className={`rounded px-3 py-1.5 text-sm ${view === key ? 'bg-slate-900 text-white' : 'border'}`}
        >
            {label}
        </Link>
    );

    return (
        <AppLayout current="audit" onSignOut={onSignOut}>
            <div className="space-y-4">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <h1 className="text-xl font-semibold">{t('nav.audit')}</h1>
                    {!signIns && <a href={`/audit/export?${exportQuery}`} className="rounded border px-3 py-1.5 text-sm">{t('audit.export')}</a>}
                </div>

                <div className="flex gap-2">{tab('log', t('audit.tabLog'))}{tab('signins', t('audit.tabSignIns'))}</div>

                <form onSubmit={submit} className="grid gap-3 rounded-lg bg-white p-4 shadow-sm sm:grid-cols-2 lg:grid-cols-4">
                    <div className="text-sm lg:col-span-2">
                        <label htmlFor="audit-search" className="sr-only">{t('audit.search')}</label>
                        <input id="audit-search" type="search" value={form.search} onChange={set('search')} placeholder={signIns ? t('audit.searchIdentifier') : t('audit.search')} className="w-full rounded border px-3 py-2" />
                    </div>
                    {!signIns && (
                        <>
                            <SelectField label={<span className="sr-only">{t('audit.action')}</span>} value={form.action} onChange={set('action')} placeholder={t('audit.allActions')} options={opt(actions)} />
                            <div className="text-sm">
                                <label htmlFor="audit-actor" className="sr-only">{t('audit.actor')}</label>
                                <input id="audit-actor" value={form.actor} onChange={set('actor')} placeholder={t('audit.actor')} className="w-full rounded border px-3 py-2" />
                            </div>
                            <SelectField label={<span className="sr-only">{t('audit.subject')}</span>} value={form.subject} onChange={set('subject')} placeholder={t('audit.allSubjects')} options={opt(subjects)} />
                        </>
                    )}
                    {signIns && <SelectField label={<span className="sr-only">{t('audit.method')}</span>} value={form.method} onChange={set('method')} placeholder={t('audit.allMethods')} options={opt(methods, 'audit.methods')} />}
                    <SelectField label={<span className="sr-only">{t('audit.result')}</span>} value={form.result} onChange={set('result')} placeholder={t('audit.allResults')} options={opt(results, 'audit.results')} />
                    <div className="text-sm">
                        <label htmlFor="audit-from">{t('audit.from')}</label>
                        <input id="audit-from" type="date" value={form.from} onChange={set('from')} className="mt-1 w-full rounded border px-3 py-2" />
                    </div>
                    <div className="text-sm">
                        <label htmlFor="audit-to">{t('audit.to')}</label>
                        <input id="audit-to" type="date" value={form.to} onChange={set('to')} className="mt-1 w-full rounded border px-3 py-2" />
                    </div>
                    <div className="flex items-end gap-2">
                        <button className="rounded bg-slate-900 px-3 py-2 text-sm text-white">{t('users.apply')}</button>
                        <button type="button" className="rounded border px-3 py-2 text-sm" onClick={() => { setForm({ search: '', action: '', actor: '', subject: '', result: '', method: '', from: '', to: '' }); onFilter?.({ view }); }}>{t('users.clear')}</button>
                    </div>
                </form>

                <div className="overflow-x-auto rounded-lg bg-white shadow-sm">
                    <table className="w-full text-left text-sm">
                        <thead className="border-b text-xs uppercase text-slate-500">
                            {signIns ? (
                                <tr><th className="p-3">{t('audit.when')}</th><th className="p-3">{t('audit.identifier')}</th><th className="p-3">{t('audit.method')}</th><th className="p-3">{t('audit.result')}</th><th className="p-3">{t('audit.reason')}</th><th className="p-3">IP</th></tr>
                            ) : (
                                <tr><th className="p-3">{t('audit.when')}</th><th className="p-3">{t('audit.actor')}</th><th className="p-3">{t('audit.action')}</th><th className="p-3">{t('audit.what')}</th><th className="p-3">{t('audit.result')}</th><th className="p-3" /></tr>
                            )}
                        </thead>
                        <tbody>
                            {entries.data.length === 0 && <tr><td colSpan={6} className="p-6 text-center text-slate-500">{t('audit.empty')}</td></tr>}
                            {entries.data.map((e) => (signIns ? (
                                <tr key={e.id} className="border-b last:border-0">
                                    <td className="p-3 whitespace-nowrap">{when(e.created_at)}</td>
                                    <td className="p-3">{e.identifier}</td>
                                    <td className="p-3">{t(`audit.methods.${e.method}`)}</td>
                                    <td className="p-3"><span className={`rounded px-2 py-0.5 text-xs ${RESULT_TONE[e.result]}`}>{t(`audit.results.${e.result}`)}</span></td>
                                    <td className="p-3 text-slate-500">{e.reason ?? '-'}</td>
                                    <td className="p-3 text-slate-500">{e.ip ?? '-'}</td>
                                </tr>
                            ) : (
                                <>
                                    <tr key={e.id} className="border-b align-top last:border-0">
                                        <td className="p-3 whitespace-nowrap">{when(e.created_at)}</td>
                                        <td className="p-3">{e.actor ? <>{e.actor.name}<div className="text-xs text-slate-500">{e.actor.staff_id}</div></> : t('audit.system')}</td>
                                        <td className="p-3 font-mono text-xs">{e.action}</td>
                                        <td className="p-3">{e.description}{e.subject && <div className="text-xs text-slate-500">{e.subject}</div>}</td>
                                        <td className="p-3"><span className={`rounded px-2 py-0.5 text-xs ${RESULT_TONE[e.result]}`}>{t(`audit.results.${e.result}`)}</span></td>
                                        <td className="p-3">
                                            {(e.old_values || e.new_values) && (
                                                <button type="button" className="text-xs underline" aria-expanded={open === e.id} onClick={() => setOpen(open === e.id ? null : e.id)}>{t('audit.details')}</button>
                                            )}
                                        </td>
                                    </tr>
                                    {open === e.id && (
                                        <tr key={`${e.id}-d`} className="border-b bg-slate-50">
                                            <td colSpan={6} className="grid gap-3 p-3 sm:grid-cols-2">
                                                <Values label={t('audit.before')} values={e.old_values} />
                                                <Values label={t('audit.after')} values={e.new_values} />
                                                <div className="text-xs text-slate-500 sm:col-span-2">IP {e.ip ?? '-'}</div>
                                            </td>
                                        </tr>
                                    )}
                                </>
                            )))}
                        </tbody>
                    </table>
                </div>

                {entries.last_page > 1 && (
                    <nav className="flex items-center justify-between text-sm" aria-label="pagination">
                        {entries.prev_page_url ? <Link href={entries.prev_page_url} className="underline">{t('users.prev')}</Link> : <span />}
                        <span>{t('users.page', { current: entries.current_page, last: entries.last_page })}</span>
                        {entries.next_page_url ? <Link href={entries.next_page_url} className="underline">{t('users.next')}</Link> : <span />}
                    </nav>
                )}
            </div>
        </AppLayout>
    );
}
