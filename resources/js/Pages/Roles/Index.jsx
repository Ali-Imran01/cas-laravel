import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import AppLayout from '../../Components/AppLayout';
import Field from '../../Components/Field';
import FlashMessages from '../../Components/FlashMessages';
import { useLink } from '../../Components/LinkContext';

const ACTION_ORDER = ['view', 'create', 'edit', 'delete', 'approve'];
const button = 'rounded border px-3 py-1 text-sm';
const primary = 'rounded bg-slate-900 px-3 py-1.5 text-sm text-white disabled:opacity-60';

function Details({ role, errors, onSubmit }) {
    const { t } = useTranslation();
    const [v, setV] = useState({ display_name: role.display_name, description: role.description ?? '' });

    return (
        <form onSubmit={(e) => { e.preventDefault(); onSubmit?.(v); }} className="grid gap-3 sm:grid-cols-2">
            <Field label={t('rolePage.displayName')} value={v.display_name} onChange={(e) => setV({ ...v, display_name: e.target.value })} error={errors.display_name} />
            <Field label={t('rolePage.description')} value={v.description} onChange={(e) => setV({ ...v, description: e.target.value })} error={errors.description} />
            <div><button className={primary}>{t('rolePage.saveDetails')}</button></div>
        </form>
    );
}

function Grid({ role, grid, grantable, editable, errors, onSave }) {
    const { t } = useTranslation();
    const [checked, setChecked] = useState(() => new Set(role.permissions));
    const actions = ACTION_ORDER.filter((a) => grid.some((row) => row.actions.includes(a)));
    const toggle = (name) => setChecked((s) => {
        const next = new Set(s);
        next.has(name) ? next.delete(name) : next.add(name);
        return next;
    });

    return (
        <form onSubmit={(e) => { e.preventDefault(); onSave?.([...checked].sort()); }} className="space-y-3">
            <div className="overflow-x-auto rounded border">
                <table className="w-full text-left text-sm">
                    <thead className="bg-slate-50 text-xs uppercase text-slate-500">
                        <tr>
                            <th className="p-2">{t('rolePage.module')}</th>
                            {actions.map((a) => <th key={a} className="p-2 text-center">{t(`rolePage.actions.${a}`)}</th>)}
                        </tr>
                    </thead>
                    <tbody>
                        {grid.map((row) => (
                            <tr key={row.module} className="border-t">
                                <th scope="row" className="p-2 font-normal">{t(`rolePage.modules.${row.module}`)}</th>
                                {actions.map((a) => {
                                    const name = `${row.module}.${a}`;
                                    return (
                                        <td key={a} className="p-2 text-center">
                                            {row.actions.includes(a) ? (
                                                <input
                                                    type="checkbox"
                                                    aria-label={`${t(`rolePage.modules.${row.module}`)}: ${t(`rolePage.actions.${a}`)}`}
                                                    checked={checked.has(name)}
                                                    disabled={!editable || (!checked.has(name) && !grantable.includes(name))}
                                                    onChange={() => toggle(name)}
                                                />
                                            ) : '—'}
                                        </td>
                                    );
                                })}
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
            {errors.permissions && <p role="alert" className="text-sm text-red-700">{errors.permissions}</p>}
            {editable && <button className={primary}>{t('rolePage.savePermissions')}</button>}
        </form>
    );
}

function NewRole({ errors, onSubmit }) {
    const { t } = useTranslation();
    const [v, setV] = useState({ name: '', display_name: '', description: '' });
    const set = (key) => (e) => setV((s) => ({ ...s, [key]: e.target.value }));

    return (
        <form onSubmit={(e) => { e.preventDefault(); onSubmit?.(v); }} className="space-y-3">
            <h2 className="font-medium">{t('rolePage.newTitle')}</h2>
            <Field label={t('rolePage.displayName')} value={v.display_name} onChange={set('display_name')} error={errors.display_name} />
            <Field label={t('rolePage.name')} value={v.name} onChange={set('name')} error={errors.name} placeholder="service_desk_agent" />
            <p className="text-xs text-slate-500">{t('rolePage.nameHint')}</p>
            <Field label={t('rolePage.description')} value={v.description} onChange={set('description')} error={errors.description} />
            <button className={primary}>{t('rolePage.create')}</button>
        </form>
    );
}

// Dumb page: onCreateRole, onUpdateRole, onSavePermissions and onDeleteRole come from the entry point;
// choosing a role is a plain link (?role=ID).
export default function Index({ roles = [], selected, grid = [], grantable = [], can = {}, flash = {}, errors = {}, onCreateRole, onUpdateRole, onSavePermissions, onDeleteRole, onSignOut }) {
    const { t } = useTranslation();
    const Link = useLink();
    const [confirmDelete, setConfirmDelete] = useState(false);
    const editable = Boolean(can.update && selected && !selected.locked_reason);

    return (
        <AppLayout current="roles" onSignOut={onSignOut}>
            <div className="space-y-4">
                <h1 className="text-xl font-semibold">{t('nav.roles')}</h1>
                <FlashMessages flash={flash} errors={errors} />

                <div className="grid gap-6 lg:grid-cols-[minmax(0,18rem)_1fr]">
                    <div className="space-y-6">
                        <nav aria-label={t('nav.roles')} className="rounded-lg bg-white p-2 shadow-sm">
                            <ul>
                                {roles.map((r) => (
                                    <li key={r.id}>
                                        <Link
                                            href={`/roles?role=${r.id}`}
                                            aria-current={selected?.id === r.id ? 'true' : undefined}
                                            className={`flex items-center justify-between rounded px-2 py-1.5 text-sm ${selected?.id === r.id ? 'bg-slate-900 text-white' : 'hover:bg-slate-100'}`}
                                        >
                                            <span>{r.display_name}{r.is_system && <span className="ml-2 text-xs opacity-70">{t('rolePage.system')}</span>}</span>
                                            <span className="text-xs opacity-70">{r.users_count}</span>
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        </nav>
                        {can.create && <div className="rounded-lg bg-white p-4 shadow-sm"><NewRole errors={errors} onSubmit={onCreateRole} /></div>}
                    </div>

                    {selected && (
                        <div className="space-y-6 rounded-lg bg-white p-4 shadow-sm">
                            <div>
                                <h2 className="text-lg font-semibold">{selected.display_name}</h2>
                                <p className="text-sm text-slate-500">
                                    <code>{selected.name}</code> · {t('rolePage.holders', { count: selected.users_count })}
                                </p>
                                {selected.locked_reason && <p role="note" className="mt-2 rounded bg-amber-50 p-2 text-sm text-amber-900">{selected.locked_reason}</p>}
                            </div>

                            {editable && <Details key={`d${selected.id}`} role={selected} errors={errors} onSubmit={onUpdateRole} />}

                            <Grid
                                key={`g${selected.id}:${selected.permissions.join(',')}`}
                                role={selected}
                                grid={grid}
                                grantable={grantable}
                                editable={editable}
                                errors={errors}
                                onSave={onSavePermissions}
                            />

                            {can.delete && !selected.is_system && (
                                confirmDelete ? (
                                    <div role="alertdialog" aria-label={t('rolePage.delete')} className="flex flex-wrap items-center gap-2 rounded bg-red-50 p-3 text-sm">
                                        <span>{t('rolePage.deleteConfirm', { name: selected.display_name })}</span>
                                        <button type="button" className="rounded bg-red-700 px-3 py-1 text-white" onClick={() => { onDeleteRole?.(); setConfirmDelete(false); }}>{t('rolePage.confirm')}</button>
                                        <button type="button" className={button} onClick={() => setConfirmDelete(false)}>{t('rolePage.cancel')}</button>
                                    </div>
                                ) : (
                                    <button type="button" className={`${button} text-red-700`} onClick={() => setConfirmDelete(true)}>{t('rolePage.delete')}</button>
                                )
                            )}
                        </div>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
