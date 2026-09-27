import { useTranslation } from 'react-i18next';
import { useLink } from '../../Components/LinkContext';

// Shown instead of sending the person back to an app: unknown or disabled app, no access, or a bad request.
export default function Denied({ reason, app }) {
    const { t } = useTranslation();
    const Link = useLink();

    return (
        <div className="flex min-h-screen items-center justify-center p-4">
            <div role="alert" className="w-full max-w-sm space-y-3 rounded-lg bg-white p-8 shadow">
                <h1 className="text-xl font-semibold">{t('oauth.title')}</h1>
                {app && <p className="text-sm text-slate-500">{app}</p>}
                <p className="text-sm">{t(`oauth.reason.${reason}`, { defaultValue: t('oauth.reason.unknown_app') })}</p>
                <Link href="/" className="inline-block text-sm underline">{t('oauth.back')}</Link>
            </div>
        </div>
    );
}
