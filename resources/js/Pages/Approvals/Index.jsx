import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import AppLayout from '../../Components/AppLayout';
import FlashMessages from '../../Components/FlashMessages';
import { useLink } from '../../Components/LinkContext';

const button = 'rounded border px-3 py-1 text-sm';
const primary = 'rounded bg-slate-900 px-3 py-1.5 text-sm text-white';
const TABS = ['decide', 'mine', 'all'];
const STATUS_TONE = {
    pending: 'bg-amber-100 text-amber-800', info_requested: 'bg-sky-100 text-sky-800',
    approved: 'bg-emerald-100 text-emerald-800', rejected: 'bg-red-100 text-red-800', cancelled: 'bg-slate-200 text-slate-700',
};

function StatusBadge({ status }) {
    const { t } = useTranslation();
    return <span className={`rounded px-2 py-0.5 text-xs ${STATUS_TONE[status]}`}>{t(`approvalsPage.status.${status}`)}</span>;
}

// The workflow's levels, with whichever one is current highlighted; done levels turn green once approved.
function StepTrail({ steps, currentLevel, status }) {
    const open = status === 'pending' || status === 'info_requested';
    return (
        <ol className="flex flex-wrap gap-2 text-xs">
            {steps.map((s) => (
                <li key={s.level} className={`rounded px-2 py-1 ${
                    s.level < currentLevel || status === 'approved' ? 'bg-emerald-100 text-emerald-800'
                        : s.level === currentLevel && open ? 'bg-amber-100 text-amber-800'
                            : 'bg-slate-100 text-slate-500'
                }`}
                >
                    {s.name}
                </li>
            ))}
        </ol>
    );
}

function Timeline({ actions }) {
    const { t, i18n } = useTranslation();
    return (
        <ol className="space-y-2 text-sm">
            {actions.map((a) => (
                <li key={a.id} className="rounded border p-2">
                    <div className="flex flex-wrap items-center justify-between gap-2">
                        <span className="font-medium">{t(`approvalsPage.decision.${a.decision}`)}</span>
                        <span className="text-xs text-slate-500">{new Date(a.created_at).toLocaleString(i18n.language)}</span>
                    </div>
                    <div className="text-slate-500">{a.actor ?? '—'}</div>
                    {a.comment && <p className="mt-1">{a.comment}</p>}
                </li>
            ))}
        </ol>
    );
}

// A comment box plus a single button; used for reject, request-info, resubmit and "add a comment".
function ActionForm({ label, placeholder, error, onRun }) {
    const [comment, setComment] = useState('');

    return (
        <div className="space-y-1">
            <textarea
                rows={2}
                value={comment}
                onChange={(e) => setComment(e.target.value)}
                placeholder={placeholder}
                aria-invalid={error ? 'true' : undefined}
                className="w-full rounded border px-3 py-2 text-sm"
            />
            {error && <p role="alert" className="text-sm text-red-700">{error}</p>}
            <button type="button" className={button} onClick={() => onRun?.(comment.trim() || null)}>{label}</button>
        </div>
    );
}

// Dumb page: choosing a tab or a request is a plain link (?tab=&request=); every decision is a callback from the entry point.
export default function Index({
    requests = [], tab = 'decide', selected, can = {}, flash = {}, errors = {},
    onApprove, onReject, onRequestInfo, onResubmit, onComment, onCancel, onSignOut,
}) {
    const { t } = useTranslation();
    const Link = useLink();
    const [confirmCancel, setConfirmCancel] = useState(false);

    return (
        <AppLayout current="approvals" onSignOut={onSignOut}>
            <div className="space-y-4">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <h1 className="text-xl font-semibold">{t('nav.approvals')}</h1>
                    <div className="flex gap-2">
                        {can.manageWorkflows && <Link href="/approvals/workflows" className={button}>{t('approvalsPage.manageWorkflows')}</Link>}
                        {can.submit && <Link href="/approvals/new" className={primary}>{t('approvalsPage.newRequest')}</Link>}
                    </div>
                </div>
                <FlashMessages flash={flash} errors={errors} />

                <div className="grid gap-6 lg:grid-cols-[minmax(0,20rem)_1fr]">
                    <div className="space-y-4">
                        <nav aria-label={t('nav.approvals')} className="flex gap-1 rounded-lg bg-white p-1 text-sm shadow-sm">
                            {TABS.filter((x) => x !== 'all' || can.oversee).map((x) => (
                                <Link key={x} href={`/approvals?tab=${x}`} className={`flex-1 rounded px-2 py-1.5 text-center ${tab === x ? 'bg-slate-900 text-white' : 'hover:bg-slate-100'}`}>
                                    {t(`approvalsPage.tabs.${x}`)}
                                </Link>
                            ))}
                        </nav>
                        <ul className="divide-y rounded-lg bg-white shadow-sm">
                            {requests.length === 0 && <li className="p-4 text-sm text-slate-500">{t('approvalsPage.empty')}</li>}
                            {requests.map((r) => {
                                const active = selected?.id === r.id;
                                return (
                                    <li key={r.id}>
                                        <Link
                                            href={`/approvals?tab=${tab}&request=${r.id}`}
                                            aria-current={active ? 'true' : undefined}
                                            className={`block space-y-1 p-3 text-sm ${active ? 'bg-slate-900 text-white' : 'hover:bg-slate-50'}`}
                                        >
                                            <div className="flex items-center justify-between gap-2">
                                                <span className="font-medium">{r.reference}</span>
                                                {active ? <span className="text-xs opacity-80">{t('approvalsPage.levelOf', { current: r.current_level, total: r.levels })}</span> : <StatusBadge status={r.status} />}
                                            </div>
                                            <div className={active ? 'opacity-90' : 'text-slate-600'}>{r.summary}</div>
                                            <div className={`text-xs ${active ? 'opacity-70' : 'text-slate-500'}`}>
                                                {t('approvalsPage.requestedBy', { name: r.requester })}
                                                {r.overdue && <span className={active ? '' : 'text-red-700'}> · {t('approvalsPage.overdue')}</span>}
                                            </div>
                                        </Link>
                                    </li>
                                );
                            })}
                        </ul>
                    </div>

                    {selected && (
                        <div className="space-y-6 rounded-lg bg-white p-4 shadow-sm">
                            <div className="space-y-2">
                                <div className="flex flex-wrap items-center gap-2">
                                    <h2 className="text-lg font-semibold">{selected.reference}</h2>
                                    <StatusBadge status={selected.status} />
                                    {selected.overdue && <span className="rounded bg-red-100 px-2 py-0.5 text-xs text-red-800">{t('approvalsPage.overdue')}</span>}
                                </div>
                                <p className="text-sm text-slate-500">
                                    {selected.workflow} · {t('approvalsPage.requestedBy', { name: selected.requester.name })}
                                    {selected.source_application && ` · ${t('approvalsPage.source', { name: selected.source_application })}`}
                                </p>
                                <p>{selected.summary}</p>
                                <StepTrail steps={selected.steps} currentLevel={selected.current_level} status={selected.status} />
                            </div>

                            <div>
                                <h3 className="text-sm font-medium text-slate-500">{t('approvalsPage.justification')}</h3>
                                <p className="text-sm">{selected.justification || t('approvalsPage.noJustification')}</p>
                            </div>

                            {Object.keys(selected.payload).length > 0 && (
                                <div>
                                    <h3 className="text-sm font-medium text-slate-500">{t('approvalsPage.payload')}</h3>
                                    <dl className="grid gap-2 rounded bg-slate-50 p-3 text-sm sm:grid-cols-2">
                                        {Object.entries(selected.payload).map(([k, v]) => (
                                            <div key={k} className="flex justify-between gap-2 sm:block">
                                                <dt className="text-slate-500">{k}</dt>
                                                <dd className="break-all">{v === null || v === '' ? '—' : String(v)}</dd>
                                            </div>
                                        ))}
                                    </dl>
                                </div>
                            )}

                            {selected.can.decide && selected.status === 'pending' && (
                                <div className="space-y-3 rounded border p-3">
                                    <h3 className="text-sm font-medium">{t('approvalsPage.tabs.decide')}</h3>
                                    <button type="button" className={primary} onClick={() => onApprove?.()}>{t('approvalsPage.approve')}</button>
                                    <ActionForm label={t('approvalsPage.reject')} placeholder={t('approvalsPage.reasonPlaceholder')} error={errors.comment} onRun={onReject} />
                                    <ActionForm label={t('approvalsPage.requestInfo')} placeholder={t('approvalsPage.reasonPlaceholder')} error={errors.comment} onRun={onRequestInfo} />
                                </div>
                            )}

                            {selected.can.requester && selected.status === 'info_requested' && (
                                <ActionForm label={t('approvalsPage.resubmit')} placeholder={t('approvalsPage.commentPlaceholder')} error={errors.comment} onRun={onResubmit} />
                            )}

                            {selected.can.comment && (
                                <ActionForm label={t('approvalsPage.addComment')} placeholder={t('approvalsPage.commentPlaceholder')} error={errors.comment} onRun={onComment} />
                            )}

                            {selected.can.requester && ['pending', 'info_requested'].includes(selected.status) && (
                                confirmCancel ? (
                                    <div role="alertdialog" aria-label={t('approvalsPage.cancelRequest')} className="flex flex-wrap items-center gap-2 rounded bg-red-50 p-3 text-sm">
                                        <span>{t('approvalsPage.confirmCancel')}</span>
                                        <button type="button" className="rounded bg-red-700 px-3 py-1 text-white" onClick={() => { onCancel?.(); setConfirmCancel(false); }}>{t('approvalsPage.confirm')}</button>
                                        <button type="button" className={button} onClick={() => setConfirmCancel(false)}>{t('approvalsPage.cancel')}</button>
                                    </div>
                                ) : (
                                    <button type="button" className={`${button} text-red-700`} onClick={() => setConfirmCancel(true)}>{t('approvalsPage.cancelRequest')}</button>
                                )
                            )}

                            <div>
                                <h3 className="mb-2 text-sm font-medium text-slate-500">{t('approvalsPage.timeline')}</h3>
                                <Timeline actions={selected.actions} />
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
