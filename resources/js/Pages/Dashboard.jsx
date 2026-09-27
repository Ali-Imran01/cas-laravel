import { useTranslation } from 'react-i18next';
import AppLayout from '../Components/AppLayout';
import { useLink } from '../Components/LinkContext';

// Dumb page: everything arrives via props. `recent` is null for people who may not read the audit log.
export default function Dashboard({ kpis, signIns = [], recent = null, onSignOut }) {
    const { t, i18n } = useTranslation();
    const Link = useLink();
    const max = Math.max(1, ...signIns.map((d) => d.count));

    return (
        <AppLayout current="dashboard" onSignOut={onSignOut}>
            <h1 className="mb-4 text-xl font-semibold">{t('dashboard.title')}</h1>
            <div className="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                {['users', 'apps', 'pendingApprovals', 'signInsToday'].map((k) => (
                    <div key={k} className="rounded-lg bg-white p-4 shadow-sm">
                        <div className="text-sm text-slate-500">{t(`dashboard.${k}`)}</div>
                        <div className="text-2xl font-semibold">{kpis[k]}</div>
                    </div>
                ))}
            </div>
            <section className="mb-6 rounded-lg bg-white p-4 shadow-sm">
                <h2 className="mb-3 text-sm font-medium text-slate-500">{t('dashboard.signIns')}</h2>
                <div className="flex h-32 items-end gap-1">
                    {signIns.map((d) => (
                        <div key={d.date} title={`${d.date}: ${d.count}`} className="flex-1 rounded-t bg-slate-700" style={{ height: `${(d.count / max) * 100}%`, minHeight: d.count ? undefined : 2 }} />
                    ))}
                </div>
            </section>
            {recent && (
                <section className="rounded-lg bg-white p-4 shadow-sm">
                    <div className="mb-2 flex items-center justify-between">
                        <h2 className="text-sm font-medium text-slate-500">{t('dashboard.recent')}</h2>
                        <Link href="/audit" className="text-sm underline">{t('dashboard.viewAll')}</Link>
                    </div>
                    {recent.length === 0 && <p className="text-sm text-slate-500">{t('audit.empty')}</p>}
                    <ul className="divide-y text-sm">
                        {recent.map((r) => (
                            <li key={r.id} className="flex flex-wrap justify-between gap-2 py-2">
                                <span>{r.description}<span className="text-slate-500"> · {r.actor ?? t('audit.system')}</span></span>
                                <span className="text-slate-500">{new Date(r.created_at).toLocaleString(i18n.language)}</span>
                            </li>
                        ))}
                    </ul>
                </section>
            )}
        </AppLayout>
    );
}
