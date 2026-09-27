import { useTranslation } from 'react-i18next';

const TONE = {
    queued: 'bg-slate-200 text-slate-700',
    processing: 'bg-sky-100 text-sky-800',
    completed: 'bg-emerald-100 text-emerald-800',
    failed: 'bg-red-100 text-red-800',
};

export default function ImportStatus({ status }) {
    const { t } = useTranslation();

    return <span className={`rounded px-2 py-0.5 text-xs ${TONE[status] ?? TONE.queued}`}>{t(`import.status.${status}`)}</span>;
}
