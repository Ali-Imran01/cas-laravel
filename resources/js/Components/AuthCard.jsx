import { useTranslation } from 'react-i18next';

/** Centered card used by every signed-out screen. `flash.status` is shown above the form. */
export default function AuthCard({ title, status, onSubmit, children }) {
    const { t } = useTranslation();

    return (
        <div className="flex min-h-screen items-center justify-center p-4">
            <form onSubmit={onSubmit} className="w-full max-w-sm space-y-4 rounded-lg bg-white p-8 shadow">
                <h1 className="text-xl font-semibold">{title}</h1>
                {status && <p role="status" className="rounded bg-emerald-50 p-2 text-sm text-emerald-800">{t(`flash.${status}`, { defaultValue: status })}</p>}
                {children}
            </form>
        </div>
    );
}
