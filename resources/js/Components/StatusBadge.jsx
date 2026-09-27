import { useTranslation } from 'react-i18next';

const TONE = {
    active: 'bg-emerald-100 text-emerald-800',
    pending: 'bg-sky-100 text-sky-800',
    locked: 'bg-amber-100 text-amber-900',
    inactive: 'bg-slate-200 text-slate-700',
};

export default function StatusBadge({ status, autoLocked }) {
    const { t } = useTranslation();

    return (
        <span className={`inline-block rounded px-2 py-0.5 text-xs ${TONE[status] ?? TONE.inactive}`}>
            {t(`users.status.${status}`)}
            {autoLocked && status === 'active' && ` (${t('users.autoLocked')})`}
        </span>
    );
}
