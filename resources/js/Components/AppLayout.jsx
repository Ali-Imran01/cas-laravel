import { useTranslation } from 'react-i18next';
import { setLocale } from '../i18n';
import { useLink } from './LinkContext';

const NAV = [
    ['dashboard', '/'],
    ['users', '/users'],
    ['roles', '/roles'],
    ['organization', '/organization'],
    ['apps', '/apps'],
    ['approvals', '/approvals'],
    ['audit', '/audit'],
    ['settings', '/settings'],
];

export default function AppLayout({ current = 'dashboard', onSignOut, children }) {
    const { t, i18n } = useTranslation();
    const Link = useLink();

    return (
        <div className="flex min-h-screen">
            <aside className="w-60 shrink-0 bg-slate-900 p-4 text-slate-200">
                <div className="mb-6 text-lg font-semibold text-white">{t('app.name')}</div>
                <nav className="space-y-1">
                    {NAV.map(([key, href]) => (
                        <Link
                            key={key}
                            href={href}
                            className={`block rounded px-3 py-2 text-sm ${key === current ? 'bg-slate-700 text-white' : 'hover:bg-slate-800'}`}
                        >
                            {t(`nav.${key}`)}
                        </Link>
                    ))}
                </nav>
            </aside>
            <div className="flex flex-1 flex-col">
                <header className="flex items-center justify-end gap-3 border-b bg-white px-6 py-3 text-sm">
                    <select
                        aria-label={t('common.language')}
                        value={i18n.language}
                        onChange={(e) => setLocale(e.target.value)}
                        className="rounded border px-2 py-1"
                    >
                        <option value="en">EN</option>
                        <option value="ms">MS</option>
                    </select>
                    <button type="button" onClick={onSignOut} className="rounded border px-3 py-1 hover:bg-slate-100">
                        {t('common.signOut')}
                    </button>
                </header>
                <main className="flex-1 p-6">{children}</main>
            </div>
        </div>
    );
}
