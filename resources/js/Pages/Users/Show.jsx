import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import AppLayout from '../../Components/AppLayout';
import Field from '../../Components/Field';
import FlashMessages from '../../Components/FlashMessages';
import { useLink } from '../../Components/LinkContext';
import SelectField from '../../Components/SelectField';
import StatusBadge from '../../Components/StatusBadge';

// Dumb page: every action is a callback from the entry point (onLock, onUnlock, onDeactivate,
// onReactivate, onInvite, onDelete, onTransfer).
export default function Show({ user, assignments = [], orgUnits = [], positions = [], can = {}, flash = {}, errors = {}, onLock, onUnlock, onDeactivate, onReactivate, onInvite, onDelete, onTransfer, onSignOut }) {
    const { t } = useTranslation();
    const Link = useLink();
    const [confirm, setConfirm] = useState(null); // 'deactivate' | 'delete'
    const [transfer, setTransfer] = useState({ org_unit_id: '', position_id: '', started_at: '' });

    const locked = user.status === 'locked' || user.auto_locked;
    const unitPositions = positions.filter((p) => String(p.org_unit_id) === String(transfer.org_unit_id));

    const submitTransfer = (e) => {
        e.preventDefault();
        onTransfer?.({ org_unit_id: transfer.org_unit_id, position_id: transfer.position_id || null, started_at: transfer.started_at || null });
    };

    const button = 'rounded border px-3 py-1 text-sm';
    const actions = [
        can.update && { key: 'edit', node: <Link href={`/users/${user.id}/edit`} className={button}>{t('users.show.edit')}</Link> },
        can.lock && user.status === 'active' && !locked && { key: 'lock', node: <button type="button" className={button} onClick={onLock}>{t('users.show.lock')}</button> },
        can.lock && locked && { key: 'unlock', node: <button type="button" className={button} onClick={onUnlock}>{t('users.show.unlock')}</button> },
        can.invite && user.status === 'pending' && { key: 'invite', node: <button type="button" className={button} onClick={onInvite}>{t('users.show.resend')}</button> },
        can.lock && user.status !== 'inactive' && { key: 'deactivate', node: <button type="button" className={button} onClick={() => setConfirm('deactivate')}>{t('users.show.deactivate')}</button> },
        can.lock && user.status === 'inactive' && { key: 'reactivate', node: <button type="button" className={button} onClick={onReactivate}>{t('users.show.reactivate')}</button> },
        can.delete && { key: 'delete', node: <button type="button" className={`${button} text-red-700`} onClick={() => setConfirm('delete')}>{t('users.show.delete')}</button> },
    ].filter(Boolean);

    return (
        <AppLayout current="users" onSignOut={onSignOut}>
            <div className="max-w-3xl space-y-6">
                <Link href="/users" className="text-sm underline">{t('users.show.back')}</Link>
                <div className="flex flex-wrap items-center gap-3">
                    <h1 className="text-xl font-semibold">{user.name}</h1>
                    <StatusBadge status={user.status} autoLocked={user.auto_locked} />
                </div>

                <FlashMessages flash={flash} errors={errors} />

                <dl className="grid gap-x-6 gap-y-2 rounded-lg bg-white p-4 text-sm shadow-sm sm:grid-cols-2">
                    <div><dt className="text-slate-500">{t('users.col.staffId')}</dt><dd>{user.staff_id}</dd></div>
                    <div><dt className="text-slate-500">{t('users.show.email')}</dt><dd>{user.email}</dd></div>
                    <div><dt className="text-slate-500">{t('users.show.unit')}</dt><dd>{user.org_unit ?? '-'}</dd></div>
                    <div><dt className="text-slate-500">{t('users.show.position')}</dt><dd>{user.position ?? '-'}</dd></div>
                    <div><dt className="text-slate-500">{t('users.show.role')}</dt><dd>{user.role ?? '-'}</dd></div>
                    <div><dt className="text-slate-500">{t('users.show.mfa')}</dt><dd>{user.mfa_enabled ? t('users.show.on') : t('users.show.off')}</dd></div>
                </dl>

                <div className="flex flex-wrap gap-2">
                    {actions.map((a) => <span key={a.key}>{a.node}</span>)}
                </div>

                {confirm && (
                    <div role="alertdialog" aria-label={t('users.confirm')} className="flex flex-wrap items-center gap-2 rounded bg-red-50 p-3 text-sm">
                        <span>{t(`users.show.${confirm}`)}: {user.name}?</span>
                        <button type="button" className="rounded bg-red-700 px-3 py-1 text-white" onClick={() => { (confirm === 'delete' ? onDelete : onDeactivate)?.(); setConfirm(null); }}>{t('users.confirm')}</button>
                        <button type="button" className={button} onClick={() => setConfirm(null)}>{t('users.cancel')}</button>
                    </div>
                )}

                <section className="rounded-lg bg-white p-4 shadow-sm">
                    <h2 className="mb-2 font-medium">{t('users.show.history')}</h2>
                    <table className="w-full text-left text-sm">
                        <thead className="text-xs uppercase text-slate-500">
                            <tr><th className="py-1">{t('users.show.unit')}</th><th>{t('users.show.position')}</th><th>{t('users.show.from')}</th><th>{t('users.show.to')}</th></tr>
                        </thead>
                        <tbody>
                            {assignments.map((a) => (
                                <tr key={a.id} className="border-t">
                                    <td className="py-1">{a.org_unit}</td>
                                    <td>{a.position ?? '-'}</td>
                                    <td>{a.started_at}</td>
                                    <td>{a.ended_at ?? t('users.show.current')}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </section>

                {can.transfer && (
                    <form onSubmit={submitTransfer} className="space-y-3 rounded-lg bg-white p-4 shadow-sm">
                        <h2 className="font-medium">{t('users.show.transfer')}</h2>
                        <SelectField
                            label={t('users.show.transferUnit')}
                            value={transfer.org_unit_id}
                            onChange={(e) => setTransfer({ ...transfer, org_unit_id: e.target.value, position_id: '' })}
                            error={errors.org_unit_id}
                            placeholder=""
                            options={orgUnits.map((u) => ({ value: u.id, label: `${'— '.repeat(u.depth)}${u.name}` }))}
                        />
                        {transfer.org_unit_id && (
                            <SelectField
                                label={t('users.show.transferPosition')}
                                value={transfer.position_id}
                                onChange={(e) => setTransfer({ ...transfer, position_id: e.target.value })}
                                error={errors.position_id}
                                placeholder={t('users.form.noPosition')}
                                options={unitPositions.map((p) => ({ value: p.id, label: p.title }))}
                            />
                        )}
                        <Field label={t('users.show.startedOn')} type="date" value={transfer.started_at} onChange={(e) => setTransfer({ ...transfer, started_at: e.target.value })} error={errors.started_at} />
                        <button className="rounded bg-slate-900 px-3 py-2 text-sm text-white">{t('users.show.transferSubmit')}</button>
                    </form>
                )}
            </div>
        </AppLayout>
    );
}
