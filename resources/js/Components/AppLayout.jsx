import { useTranslation } from 'react-i18next';
import { setLocale } from '../i18n';
import { useCan } from './AccessContext';
import { useLink } from './LinkContext';

// [key, path, permission needed to see the link (none for the dashboard)]
const NAV = [
    ['dashboard', '/'],
    ['users', '/users', 'users.view'],
    ['roles', '/roles', 'roles.view'],
    ['organization', '/organization', 'organization.view'],
    ['apps', '/apps', 'apps.view'],
    ['approvals', '/approvals', 'approvals.view'],
    ['audit', '/audit', 'audit.view'],
    ['settings', '/settings', 'settings.view'],
];

export default function AppLayout({ current = 'dashboard', onSignOut, children }) {
    const { t, i18n } = useTranslation();
    const Link = useLink();
    const can = useCan();

    return (
        <div className="flex min-h-screen">
            <aside className="w-60 shrink-0 bg-slate-900 p-4 text-slate-200">
                <div className="mb-6 text-lg font-semibold text-white">{t('app.name')}</div>
                <nav className="space-y-1">
                    {NAV.filter(([, , permission]) => !permission || can(permission)).map(([key, href]) => (
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
                    <Link href="/mfa" className="underline">{t('common.security')}</Link>
                    <button type="button" onClick={onSignOut} className="rounded border px-3 py-1 hover:bg-slate-100">
                        {t('common.signOut')}
                    </button>
                </header>
                <main className="flex-1 p-6">{children}</main>
            </div>
        </div>
    );
}
