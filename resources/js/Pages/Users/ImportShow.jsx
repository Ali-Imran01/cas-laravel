import { useEffect } from 'react';
import { useTranslation } from 'react-i18next';
import AppLayout from '../../Components/AppLayout';
import ImportStatus from '../../Components/ImportStatus';
import { useLink } from '../../Components/LinkContext';

const ACTIVE = ['queued', 'processing'];

// Dumb page: onRefresh() reloads the import's own data; it is called every 2 s while the job runs.
export default function ImportShow({ import: run, onRefresh, onSignOut }) {
    const { t } = useTranslation();
    const Link = useLink();
    const active = ACTIVE.includes(run.status);

    useEffect(() => {
        if (!active || !onRefresh) return undefined;
        const timer = setInterval(onRefresh, 2000);
        return () => clearInterval(timer);
    }, [active, onRefresh]);

    const done = run.success_rows + run.failed_rows;

    return (
        <AppLayout current="users" onSignOut={onSignOut}>
            <div className="max-w-3xl space-y-6">
                <Link href="/users/import" className="text-sm underline">{t('import.back')}</Link>
                <div className="flex items-center gap-3">
                    <h1 className="text-xl font-semibold">{t('import.result')}</h1>
                    <ImportStatus status={run.status} />
                </div>

                <dl className="grid gap-4 rounded-lg bg-white p-4 text-sm shadow-sm sm:grid-cols-3">
                    <div><dt className="text-slate-500">{t('import.created')}</dt><dd className="text-lg font-semibold">{run.success_rows}</dd></div>
                    <div><dt className="text-slate-500">{t('import.failed')}</dt><dd className="text-lg font-semibold">{run.failed_rows}</dd></div>
                    <div><dt className="text-slate-500">{t('import.rows')}</dt><dd className="text-lg font-semibold">{done}</dd></div>
                </dl>
                {active && <p role="status" className="text-sm text-slate-600">{t('import.working')}</p>}

                {run.errors_total > 0 && (
                    <section className="rounded-lg bg-white p-4 shadow-sm">
                        <div className="mb-2 flex flex-wrap items-center justify-between gap-2">
                            <h2 className="font-medium">{t('import.errors', { count: run.errors_total })}</h2>
                            <a href={`/users/imports/${run.id}/errors.csv`} className="text-sm underline">{t('import.download')}</a>
                        </div>
                        <table className="w-full text-left text-sm">
                            <thead className="text-xs uppercase text-slate-500">
                                <tr><th className="py-1">{t('import.row')}</th><th>{t('import.field')}</th><th>{t('import.message')}</th></tr>
                            </thead>
                            <tbody>
                                {run.errors.map((e, i) => (
                                    <tr key={i} className="border-t align-top">
                                        <td className="py-1 pr-3">{e.row ?? '-'}</td>
                                        <td className="pr-3 font-mono text-xs">{e.field}</td>
                                        <td>{e.message}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                        {run.errors_total > run.errors.length && <p className="mt-2 text-xs text-slate-500">{t('import.moreInFile', { shown: run.errors.length })}</p>}
                    </section>
                )}
            </div>
        </AppLayout>
    );
}
