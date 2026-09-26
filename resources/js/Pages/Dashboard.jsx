import { useTranslation } from 'react-i18next';
import AppLayout from '../Components/AppLayout';

// Dumb page: everything arrives via props.
export default function Dashboard({ kpis, signIns = [], onSignOut }) {
    const { t } = useTranslation();
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
            <section className="rounded-lg bg-white p-4 shadow-sm">
                <h2 className="mb-3 text-sm font-medium text-slate-500">{t('dashboard.signIns')}</h2>
                <div className="flex h-32 items-end gap-1">
                    {signIns.map((d) => (
                        <div key={d.date} title={`${d.date}: ${d.count}`} className="flex-1 rounded-t bg-slate-700" style={{ height: `${(d.count / max) * 100}%` }} />
                    ))}
                </div>
            </section>
        </AppLayout>
    );
}
