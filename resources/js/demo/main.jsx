import '../../css/app.css';
import '../i18n';
import { createRoot } from 'react-dom/client';
import { BrowserRouter, Link, Navigate, Route, Routes, useNavigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { LinkProvider } from '../Components/LinkContext';
import Dashboard from '../Pages/Dashboard';
import Login from '../Pages/Login';
import MfaChallenge from '../Pages/MfaChallenge';
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

createRoot(document.getElementById('root')).render(
    <LinkProvider value={RouterLink}>
        <BrowserRouter>
            <DemoBanner />
            <Routes>
                <Route path="/login" element={<LoginRoute />} />
                <Route path="/login/mfa" element={<MfaRoute />} />
                <Route path="/" element={<DashboardRoute />} />
                <Route path="*" element={<Navigate to="/" replace />} />
            </Routes>
            <DemoFooter />
        </BrowserRouter>
    </LinkProvider>,
);
