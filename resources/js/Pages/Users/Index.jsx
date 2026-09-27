import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import AppLayout from '../../Components/AppLayout';
import FlashMessages from '../../Components/FlashMessages';
import { useLink } from '../../Components/LinkContext';
import SelectField from '../../Components/SelectField';
import StatusBadge from '../../Components/StatusBadge';

const BULK = ['lock', 'unlock', 'deactivate'];
const SORTABLE = { name: 'col.name', staff_id: 'col.staffId', status: 'col.status', last_login_at: 'col.lastLogin' };

// Dumb page: onFilter(filters) and onBulk({action, ids}) come from the entry point.
export default function Index({ users, filters = {}, orgUnits = [], roles = [], statuses = [], can = {}, flash = {}, errors = {}, onFilter, onBulk, onSignOut }) {
    const { t, i18n } = useTranslation();
    const Link = useLink();
    const [form, setForm] = useState({ search: filters.search ?? '', status: filters.status ?? '', org_unit: filters.org_unit ?? '', role: filters.role ?? '' });
    const [selected, setSelected] = useState([]);
    const [pending, setPending] = useState(null); // bulk action awaiting confirmation

    const rows = users.data;
    const set = (key) => (e) => setForm((f) => ({ ...f, [key]: e.target.value }));
    const apply = (extra = {}) => onFilter?.({ ...form, sort: filters.sort, dir: filters.dir, ...extra });
    const sortBy = (key) => apply({ sort: key, dir: filters.sort === key && filters.dir !== 'desc' ? 'desc' : 'asc' });
    const allSelected = rows.length > 0 && rows.every((u) => selected.includes(u.id));
    const toggle = (id) => setSelected((s) => (s.includes(id) ? s.filter((x) => x !== id) : [...s, id]));

    const runBulk = () => {
        onBulk?.({ action: pending, ids: selected });
        setPending(null);
        setSelected([]);
    };

    return (
        <AppLayout current="users" onSignOut={onSignOut}>
            <div className="space-y-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-xl font-semibold">{t('users.title')}</h1>
                    {can.create && (
                        <div className="flex gap-2">
                            <Link href="/users/import" className="rounded border px-3 py-2 text-sm">{t('users.import')}</Link>
                            <Link href="/users/create" className="rounded bg-slate-900 px-3 py-2 text-sm text-white">{t('users.new')}</Link>
                        </div>
                    )}
                </div>

                <FlashMessages flash={flash} errors={errors} />

                <form
                    onSubmit={(e) => { e.preventDefault(); apply(); }}
                    className="grid gap-3 rounded-lg bg-white p-4 shadow-sm sm:grid-cols-2 lg:grid-cols-5"
                >
                    <div className="text-sm lg:col-span-2">
                        <label htmlFor="user-search" className="sr-only">{t('users.search')}</label>
                        <input id="user-search" type="search" value={form.search} onChange={set('search')} placeholder={t('users.search')} className="w-full rounded border px-3 py-2" />
                    </div>
                    <SelectField label={<span className="sr-only">{t('users.col.status')}</span>} value={form.status} onChange={set('status')} placeholder={t('users.allStatuses')} options={statuses.map((s) => ({ value: s, label: t(`users.status.${s}`) }))} />
                    <SelectField label={<span className="sr-only">{t('users.col.unit')}</span>} value={form.org_unit} onChange={set('org_unit')} placeholder={t('users.allUnits')} options={orgUnits.map((u) => ({ value: u.id, label: `${'— '.repeat(u.depth)}${u.name}` }))} />
                    <SelectField label={<span className="sr-only">{t('users.col.role')}</span>} value={form.role} onChange={set('role')} placeholder={t('users.allRoles')} options={roles.map((r) => ({ value: r.name, label: r.display_name }))} />
                    <div className="flex gap-2 sm:col-span-2 lg:col-span-5">
                        <button className="rounded bg-slate-900 px-3 py-2 text-sm text-white">{t('users.apply')}</button>
                        <button
                            type="button"
                            className="rounded border px-3 py-2 text-sm"
                            onClick={() => { setForm({ search: '', status: '', org_unit: '', role: '' }); onFilter?.({}); }}
                        >
                            {t('users.clear')}
                        </button>
                    </div>
                </form>

                {selected.length > 0 && (
                    <div className="flex flex-wrap items-center gap-2 rounded bg-slate-100 p-3 text-sm">
                        <span>{t('users.selected', { count: selected.length })}</span>
                        {pending ? (
                            <>
                                <button type="button" onClick={runBulk} className="rounded bg-red-700 px-3 py-1 text-white">{t('users.confirm')}: {t(`users.bulk.${pending}`)}</button>
                                <button type="button" onClick={() => setPending(null)} className="rounded border px-3 py-1">{t('users.cancel')}</button>
                            </>
                        ) : (
                            BULK.map((a) => <button key={a} type="button" onClick={() => setPending(a)} className="rounded border px-3 py-1">{t(`users.bulk.${a}`)}</button>)
                        )}
                    </div>
                )}

                <div className="overflow-x-auto rounded-lg bg-white shadow-sm">
                    <table className="w-full text-left text-sm">
                        <thead className="border-b text-xs uppercase text-slate-500">
                            <tr>
                                <th className="p-3">
                                    <input type="checkbox" aria-label={t('users.selectAll')} checked={allSelected} onChange={() => setSelected(allSelected ? [] : rows.map((u) => u.id))} />
                                </th>
                                {Object.entries(SORTABLE).map(([key, label]) => (
                                    <th key={key} className="p-3" aria-sort={filters.sort === key ? (filters.dir === 'desc' ? 'descending' : 'ascending') : undefined}>
                                        <button type="button" onClick={() => sortBy(key)} className="uppercase">{t(`users.${label}`)}</button>
                                    </th>
                                ))}
                                <th className="p-3">{t('users.col.unit')}</th>
                                <th className="p-3">{t('users.col.role')}</th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.length === 0 && (
                                <tr><td colSpan={7} className="p-6 text-center text-slate-500">{t('users.empty')}</td></tr>
                            )}
                            {rows.map((u) => (
                                <tr key={u.id} className="border-b last:border-0">
                                    <td className="p-3">
                                        <input type="checkbox" aria-label={u.name} checked={selected.includes(u.id)} onChange={() => toggle(u.id)} />
                                    </td>
                                    <td className="p-3">
                                        <Link href={`/users/${u.id}`} className="font-medium underline">{u.name}</Link>
                                        <div className="text-xs text-slate-500">{u.email}</div>
                                    </td>
                                    <td className="p-3">{u.staff_id}</td>
                                    <td className="p-3"><StatusBadge status={u.status} autoLocked={u.auto_locked} /></td>
                                    <td className="p-3">{u.last_login_at ? new Date(u.last_login_at).toLocaleString(i18n.language) : t('users.never')}</td>
                                    <td className="p-3">{u.org_unit ?? '-'}</td>
                                    <td className="p-3">{u.role ?? '-'}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {users.last_page > 1 && (
                    <nav className="flex items-center justify-between text-sm" aria-label="pagination">
                        {users.prev_page_url ? <Link href={users.prev_page_url} className="underline">{t('users.prev')}</Link> : <span />}
                        <span>{t('users.page', { current: users.current_page, last: users.last_page })}</span>
                        {users.next_page_url ? <Link href={users.next_page_url} className="underline">{t('users.next')}</Link> : <span />}
                    </nav>
                )}
            </div>
        </AppLayout>
    );
}
