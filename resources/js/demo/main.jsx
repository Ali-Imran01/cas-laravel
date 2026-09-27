import '../../css/app.css';
import '../i18n';
import { useState } from 'react';
import { createRoot } from 'react-dom/client';
import { BrowserRouter, Link, Navigate, Route, Routes, useNavigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { LinkProvider } from '../Components/LinkContext';
import Dashboard from '../Pages/Dashboard';
import Login from '../Pages/Login';
import MfaChallenge from '../Pages/MfaChallenge';
import UsersIndex from '../Pages/Users/Index';
import seed from './data/seed.json';
import { resetDemo, useDemo } from './store';

const SESSION = 'cas:session';
const REPO_URL = 'https://github.com/Ali-Imran01/cas-laravel';

const isSignedIn = () => {
    try {
        return sessionStorage.getItem(SESSION) === '1';
    } catch {
        return false;
    }
};

const setSignedIn = (on) => {
    try {
        on ? sessionStorage.setItem(SESSION, '1') : sessionStorage.removeItem(SESSION);
    } catch {
        // session storage unavailable: sign-in lasts only for this page view
    }
};

const RouterLink = ({ href, ...props }) => <Link to={href} {...props} />;

function DemoBanner() {
    const { t } = useTranslation();
    return (
        <div className="sticky top-0 z-50 flex items-center justify-center gap-3 bg-amber-100 px-4 py-2 text-sm text-amber-900">
            <span>{t('demo.banner')}</span>
            <button type="button" onClick={resetDemo} className="rounded border border-amber-400 px-2 py-0.5 hover:bg-amber-200">
                {t('demo.reset')}
            </button>
        </div>
    );
}

function DemoFooter() {
    const { t } = useTranslation();
    return (
        <footer className="border-t bg-white px-6 py-3 text-center text-sm">
            <a href={REPO_URL} className="text-slate-600 underline">{t('demo.source')}</a>
        </footer>
    );
}

// Any credentials work; the MFA step is UI only.
function LoginRoute() {
    const navigate = useNavigate();
    return <Login onSubmit={() => navigate('/login/mfa')} />;
}

function MfaRoute() {
    const navigate = useNavigate();
    const done = () => {
        setSignedIn(true);
        navigate('/');
    };
    return <MfaChallenge onVerify={done} />;
}

function DashboardRoute() {
    const navigate = useNavigate();
    const users = useDemo('users').items;
    const apps = useDemo('applications').items;
    const requests = useDemo('approval_requests').items;
    if (!isSignedIn()) return <Navigate to="/login" replace />;

    const today = new Date();
    const signIns = seed.sign_in_counts.map((count, i) => {
        const d = new Date(today);
        d.setDate(today.getDate() - (seed.sign_in_counts.length - 1 - i));
        return { date: d.toISOString().slice(0, 10), count };
    });
    const kpis = {
        users: users.length,
        apps: apps.length,
        pendingApprovals: requests.filter((r) => r.status === 'pending').length,
        signInsToday: signIns.at(-1).count,
    };
    const signOut = () => {
        setSignedIn(false);
        navigate('/login');
    };
    return <Dashboard kpis={kpis} signIns={signIns} onSignOut={signOut} />;
}

const ROLES = [
    { name: 'super_admin', display_name: 'Super admin' },
    { name: 'hr_officer', display_name: 'HR officer' },
    { name: 'dept_head', display_name: 'Department head' },
    { name: 'staff', display_name: 'Staff' },
];
const STATUSES = ['pending', 'active', 'locked', 'inactive'];
const BULK_STATUS = { lock: 'locked', unlock: 'active', deactivate: 'inactive' };

// Same Users/Index page as the real app; filtering, sorting and bulk actions run on the sample data.
function UsersRoute() {
    const navigate = useNavigate();
    const { items, update } = useDemo('users');
    const units = useDemo('org_units').items;
    const [filters, setFilters] = useState({});
    const [flash, setFlash] = useState({});
    if (!isSignedIn()) return <Navigate to="/login" replace />;

    const unitName = (id) => units.find((u) => u.id === id)?.name ?? null;
    const depth = (u) => (u.parent_id ? 1 + depth(units.find((p) => p.id === u.parent_id)) : 0);
    const inTree = (rootId, id) => id === rootId || units.some((u) => u.id === id && u.parent_id && inTree(rootId, u.parent_id));
    const term = (filters.search ?? '').toLowerCase();

    const rows = items
        .filter((u) => !term || [u.name, u.staff_id, u.email].some((v) => v.toLowerCase().includes(term)))
        .filter((u) => !filters.status || u.status === filters.status)
        .filter((u) => !filters.role || u.role === filters.role)
        .filter((u) => !filters.org_unit || inTree(Number(filters.org_unit), u.org_unit_id))
        .sort((a, b) => {
            const key = filters.sort ?? 'name';
            const order = String(a[key] ?? '').localeCompare(String(b[key] ?? ''));
            return filters.dir === 'desc' ? -order : order;
        })
        .map((u) => ({ ...u, org_unit: unitName(u.org_unit_id), auto_locked: false, last_login_at: null, can: {} }));

    const onBulk = ({ action, ids }) => {
        ids.forEach((id) => update(id, { status: BULK_STATUS[action] }));
        setFlash({ status: `${ids.length} updated, 0 skipped.` });
    };

    return (
        <UsersIndex
            users={{ data: rows, current_page: 1, last_page: 1 }}
            filters={filters}
            orgUnits={units.map((u) => ({ id: u.id, name: u.name, depth: depth(u) }))}
            roles={ROLES}
            statuses={STATUSES}
            can={{ create: false }}
            flash={flash}
            onFilter={setFilters}
            onBulk={onBulk}
            onSignOut={() => { setSignedIn(false); navigate('/login'); }}
        />
    );
}

createRoot(document.getElementById('root')).render(
    <LinkProvider value={RouterLink}>
        <BrowserRouter>
            <DemoBanner />
            <Routes>
                <Route path="/login" element={<LoginRoute />} />
                <Route path="/login/mfa" element={<MfaRoute />} />
                <Route path="/" element={<DashboardRoute />} />
                <Route path="/users" element={<UsersRoute />} />
                <Route path="*" element={<Navigate to="/" replace />} />
            </Routes>
            <DemoFooter />
        </BrowserRouter>
    </LinkProvider>,
);
