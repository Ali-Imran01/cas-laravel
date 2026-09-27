import '../../css/app.css';
import '../i18n';
import { useState } from 'react';
import { createRoot } from 'react-dom/client';
import { BrowserRouter, Link, Navigate, Route, Routes, useNavigate, useSearchParams } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { LinkProvider } from '../Components/LinkContext';
import AppsIndex from '../Pages/Apps/Index';
import AuditIndex from '../Pages/Audit/Index';
import Dashboard from '../Pages/Dashboard';
import Login from '../Pages/Login';
import MfaChallenge from '../Pages/MfaChallenge';
import OrganizationIndex from '../Pages/Organization/Index';
import RolesIndex from '../Pages/Roles/Index';
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

// Read-only view of the same Audit/Index page over a few sample entries; filters and export are real-app only.
function AuditRoute() {
    const navigate = useNavigate();
    if (!isSignedIn()) return <Navigate to="/login" replace />;

    const now = Date.now();
    const data = seed.audit_logs.map((e) => ({ ...e, created_at: new Date(now - e.hours_ago * 3600 * 1000).toISOString() }));

    return (
        <AuditIndex
            entries={{ data, current_page: 1, last_page: 1 }}
            actions={[...new Set(data.map((e) => e.action))].sort()}
            subjects={['OrgUnit', 'Role', 'User']}
            results={['success', 'failed', 'blocked']}
            methods={['password', 'sso', 'mfa']}
            onSignOut={() => { setSignedIn(false); navigate('/login'); }}
        />
    );
}

const SCOPES = [
    { name: 'openid', description: 'Sign in and identify the user' }, { name: 'profile', description: 'Name, staff ID, organization unit and app role' },
    { name: 'email', description: 'Email address' }, { name: 'org.read', description: 'Read the organization structure' },
    { name: 'users.read', description: 'Read the staff directory' }, { name: 'approvals', description: 'Submit approval requests' },
];

// Read-only view of the same Apps/Index page: no `can` flags, so no forms, secrets or access editing.
function AppsRoute() {
    const navigate = useNavigate();
    const [params] = useSearchParams();
    const apps = useDemo('applications').items;
    if (!isSignedIn()) return <Navigate to="/login" replace />;

    const current = apps.find((a) => a.id === Number(params.get('app'))) ?? apps[0];
    const selected = current && {
        ...current, homepage_url: null, color: null, allowed_scopes: ['openid', 'profile', 'email'], owner: 'Aisyah Rahman', secret_rotated_at: null,
        client_id: `00000000-0000-4000-8000-00000000000${current.id}`, redirect_uris: [`https://${current.code}.example.com/callback`], roles: [], grants: [],
    };

    return (
        <AppsIndex
            apps={apps}
            selected={selected}
            scopes={SCOPES}
            defaultScopes={['openid', 'profile', 'email']}
            endpoints={{ discovery: 'https://cas.example.com/.well-known/openid-configuration', authorization: 'https://cas.example.com/oauth/authorize', token: 'https://cas.example.com/oauth/token', userinfo: 'https://cas.example.com/oauth/userinfo' }}
            can={{}}
            onSignOut={() => { setSignedIn(false); navigate('/login'); }}
        />
    );
}

const MODULES = ['users', 'roles', 'organization', 'apps', 'approvals', 'audit', 'settings'];
const GRID = MODULES.map((module) => ({ module, actions: ['view', 'create', 'edit', 'delete', ...(module === 'approvals' ? ['approve'] : [])] }));
const ALL_PERMISSIONS = GRID.flatMap((r) => r.actions.map((a) => `${r.module}.${a}`));
const ROLE_PERMISSIONS = {
    super_admin: ALL_PERMISSIONS,
    hr_officer: ['users.view', 'users.create', 'users.edit', 'organization.view'],
    dept_head: ['users.view', 'approvals.view', 'approvals.approve'],
    staff: [],
};

// Read-only view of the same Roles/Index page: the sample data has the four built-in roles.
function RolesRoute() {
    const navigate = useNavigate();
    const [params] = useSearchParams();
    const users = useDemo('users').items;
    if (!isSignedIn()) return <Navigate to="/login" replace />;

    const roles = ROLES.map((r, i) => ({ id: i + 1, name: r.name, display_name: r.display_name, is_system: true, users_count: users.filter((u) => u.role === r.name).length, permissions_count: ROLE_PERMISSIONS[r.name].length }));
    const current = roles.find((r) => r.id === Number(params.get('role'))) ?? roles[0];
    const selected = { ...current, description: null, permissions: ROLE_PERMISSIONS[current.name], locked_reason: null };

    return (
        <RolesIndex
            roles={roles}
            selected={selected}
            grid={GRID}
            grantable={[]}
            can={{}}
            onSignOut={() => { setSignedIn(false); navigate('/login'); }}
        />
    );
}

// Read-only view of the same Organization/Index page (no `can` flags, so no edit forms).
function OrganizationRoute() {
    const navigate = useNavigate();
    const [params] = useSearchParams();
    const units = useDemo('org_units').items;
    const users = useDemo('users').items;
    const positions = useDemo('positions').items;
    if (!isSignedIn()) return <Navigate to="/login" replace />;

    const kids = (id) => units.filter((u) => u.parent_id === id);
    const walk = (parentId, depth) => kids(parentId).flatMap((u) => [{ ...u, depth }, ...walk(u.id, depth + 1)]);
    const tree = (id) => [id, ...kids(id).flatMap((u) => tree(u.id))];
    const ancestors = (u) => (u.parent_id ? [...ancestors(units.find((p) => p.id === u.parent_id)), units.find((p) => p.id === u.parent_id).name] : []);

    const outline = walk(null, 0).map((u) => ({
        id: u.id, name: u.name, depth: u.depth, parent_id: u.parent_id, type: u.type, code: u.code, is_active: u.is_active,
        users_count: users.filter((m) => m.org_unit_id === u.id).length,
        head: users.find((m) => m.id === u.head_user_id)?.name ?? null,
    }));
    const current = units.find((u) => u.id === Number(params.get('unit'))) ?? units.find((u) => u.parent_id === null);
    const members = current ? users.filter((m) => m.status === 'active' && tree(current.id).includes(m.org_unit_id)) : [];
    const selected = current && {
        ...current,
        cost_centre: null,
        path: ancestors(current),
        children_count: kids(current.id).length,
        users_count: users.filter((m) => m.org_unit_id === current.id).length,
        positions: positions.filter((p) => p.org_unit_id === current.id).map((p) => ({ ...p, filled: users.filter((m) => m.position_id === p.id).length })),
        members: members.map((m) => ({ id: m.id, name: m.name, staff_id: m.staff_id })),
    };

    return (
        <OrganizationIndex
            units={outline}
            selected={selected}
            types={['headquarters', 'division', 'unit', 'state_office']}
            can={{}}
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
                <Route path="/organization" element={<OrganizationRoute />} />
                <Route path="/roles" element={<RolesRoute />} />
                <Route path="/audit" element={<AuditRoute />} />
                <Route path="/apps" element={<AppsRoute />} />
                <Route path="*" element={<Navigate to="/" replace />} />
            </Routes>
            <DemoFooter />
        </BrowserRouter>
    </LinkProvider>,
);
