import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import AppLayout from '../../Components/AppLayout';
import Field from '../../Components/Field';
import FlashMessages from '../../Components/FlashMessages';
import { useLink } from '../../Components/LinkContext';
import SelectField from '../../Components/SelectField';

const indent = (unit) => `${'— '.repeat(unit.depth)}${unit.name}`;
const button = 'rounded border px-3 py-1 text-sm';
const primary = 'rounded bg-slate-900 px-3 py-1.5 text-sm text-white';

function UnitForm({ unit, types, errors, onSubmit }) {
    const { t } = useTranslation();
    const [v, setV] = useState({ type: unit.type, code: unit.code, name: unit.name, cost_centre: unit.cost_centre ?? '', is_active: unit.is_active, head_user_id: unit.head_user_id ?? '' });
    const set = (key) => (e) => setV((s) => ({ ...s, [key]: e.target.type === 'checkbox' ? e.target.checked : e.target.value }));

    return (
        <form onSubmit={(e) => { e.preventDefault(); onSubmit?.({ ...v, head_user_id: v.head_user_id || null }); }} className="space-y-3">
            <div className="grid gap-3 sm:grid-cols-2">
                <Field label={t('org.name')} value={v.name} onChange={set('name')} error={errors.name} />
                <Field label={t('org.code')} value={v.code} onChange={set('code')} error={errors.code} maxLength={20} />
                <SelectField label={t('org.type')} value={v.type} onChange={set('type')} error={errors.type} options={types.map((x) => ({ value: x, label: t(`org.types.${x}`) }))} />
                <Field label={t('org.costCentre')} value={v.cost_centre} onChange={set('cost_centre')} error={errors.cost_centre} maxLength={30} />
                <SelectField label={t('org.head')} value={v.head_user_id} onChange={set('head_user_id')} error={errors.head_user_id} placeholder={t('org.noHead')} options={unit.members.map((m) => ({ value: m.id, label: `${m.name} (${m.staff_id})` }))} />
            </div>
            <label className="flex items-center gap-2 text-sm">
                <input type="checkbox" checked={v.is_active} onChange={set('is_active')} />
                {t('org.active')}
            </label>
            <button className={primary}>{t('org.save')}</button>
        </form>
    );
}

function AddUnit({ parent, types, errors, onSubmit }) {
    const { t } = useTranslation();
    const [v, setV] = useState({ type: parent ? 'unit' : 'headquarters', code: '', name: '', cost_centre: '' });
    const set = (key) => (e) => setV((s) => ({ ...s, [key]: e.target.value }));

    return (
        <form onSubmit={(e) => { e.preventDefault(); onSubmit?.({ ...v, parent_id: parent?.id ?? null }); }} className="space-y-3">
            <h3 className="font-medium">{parent ? t('org.addChild', { name: parent.name }) : t('org.addRoot')}</h3>
            <div className="grid gap-3 sm:grid-cols-2">
                <Field label={t('org.name')} value={v.name} onChange={set('name')} error={errors.name} />
                <Field label={t('org.code')} value={v.code} onChange={set('code')} error={errors.code} maxLength={20} />
                <SelectField label={t('org.type')} value={v.type} onChange={set('type')} error={errors.type ?? errors.parent_id} options={types.map((x) => ({ value: x, label: t(`org.types.${x}`) }))} />
                <Field label={t('org.costCentre')} value={v.cost_centre} onChange={set('cost_centre')} error={errors.cost_centre} maxLength={30} />
            </div>
            <button className={primary}>{t('org.create')}</button>
        </form>
    );
}

function Positions({ unit, errors, canEdit, onSave, onDelete }) {
    const { t } = useTranslation();
    const blank = { title: '', grade: '', headcount: 1 };
    const [draft, setDraft] = useState(blank);
    const [editing, setEditing] = useState(null); // {id, title, grade, headcount}
    const [removing, setRemoving] = useState(null);
    const form = editing ?? draft;
    const setForm = editing ? setEditing : setDraft;
    const set = (key) => (e) => setForm({ ...form, [key]: e.target.value });

    const submit = (e) => {
        e.preventDefault();
        onSave?.({ id: editing?.id, title: form.title, grade: form.grade || null, headcount: Number(form.headcount) });
        setEditing(null);
        setDraft(blank);
    };

    return (
        <section className="space-y-3">
            <h3 className="font-medium">{t('org.positions')}</h3>
            {unit.positions.length === 0 && <p className="text-sm text-slate-500">{t('org.noPositions')}</p>}
            <ul className="divide-y rounded border bg-white text-sm">
                {unit.positions.map((p) => (
                    <li key={p.id} className="flex flex-wrap items-center justify-between gap-2 p-2">
                        <span>{p.title}{p.grade && <span className="text-slate-500"> ({p.grade})</span>}</span>
                        <span className="flex items-center gap-2">
                            <span className="text-slate-500">{t('org.filled', { filled: p.filled, headcount: p.headcount })}</span>
                            {canEdit && (removing === p.id ? (
                                <>
                                    <button type="button" className={`${button} bg-red-700 text-white`} onClick={() => { onDelete?.(p.id); setRemoving(null); }}>{t('org.confirm')}</button>
                                    <button type="button" className={button} onClick={() => setRemoving(null)}>{t('org.cancel')}</button>
                                </>
                            ) : (
                                <>
                                    <button type="button" className={button} onClick={() => setEditing({ id: p.id, title: p.title, grade: p.grade ?? '', headcount: p.headcount })}>{t('org.edit')}</button>
                                    <button type="button" className={`${button} text-red-700`} onClick={() => setRemoving(p.id)}>{t('org.delete')}</button>
                                </>
                            ))}
                        </span>
                    </li>
                ))}
            </ul>
            {canEdit && (
                <form onSubmit={submit} className="grid gap-3 sm:grid-cols-4">
                    <Field label={t('org.positionTitle')} value={form.title} onChange={set('title')} error={errors.title} />
                    <Field label={t('org.grade')} value={form.grade} onChange={set('grade')} error={errors.grade} maxLength={10} />
                    <Field label={t('org.headcount')} type="number" min="1" value={form.headcount} onChange={set('headcount')} error={errors.headcount} />
                    <div className="flex items-end gap-2">
                        <button className={primary}>{editing ? t('org.save') : t('org.addPosition')}</button>
                        {editing && <button type="button" className={button} onClick={() => setEditing(null)}>{t('org.cancel')}</button>}
                    </div>
                </form>
            )}
        </section>
    );
}

// Dumb page: onCreateUnit, onUpdateUnit, onMoveUnit, onDeleteUnit, onSavePosition and onDeletePosition
// come from the entry point; selecting a unit is a plain link (?unit=ID).
export default function Index({ units = [], selected, types = [], can = {}, flash = {}, errors = {}, onCreateUnit, onUpdateUnit, onMoveUnit, onDeleteUnit, onSavePosition, onDeletePosition, onSignOut }) {
    const { t } = useTranslation();
    const Link = useLink();
    const [confirmDelete, setConfirmDelete] = useState(false);
    const [parentId, setParentId] = useState('');

    // A unit cannot move under itself or its own sub-units: those follow it in tree order at greater depth.
    const invalidParents = new Set();
    if (selected) {
        let inside = false;
        for (const u of units) {
            if (u.id === selected.id) { inside = true; invalidParents.add(u.id); continue; }
            if (inside && u.depth > units.find((x) => x.id === selected.id).depth) invalidParents.add(u.id);
            else inside = false;
        }
    }

    return (
        <AppLayout current="organization" onSignOut={onSignOut}>
            <div className="space-y-4">
                <h1 className="text-xl font-semibold">{t('nav.organization')}</h1>
                <FlashMessages flash={flash} errors={errors} />

                <div className="grid gap-6 lg:grid-cols-[minmax(0,18rem)_1fr]">
                    <nav aria-label={t('nav.organization')} className="rounded-lg bg-white p-2 shadow-sm">
                        {units.length === 0 && <p className="p-2 text-sm text-slate-500">{t('org.empty')}</p>}
                        <ul>
                            {units.map((u) => (
                                <li key={u.id} style={{ paddingLeft: `${u.depth * 1.25}rem` }}>
                                    <Link
                                        href={`/organization?unit=${u.id}`}
                                        aria-current={selected?.id === u.id ? 'true' : undefined}
                                        className={`block rounded px-2 py-1 text-sm ${selected?.id === u.id ? 'bg-slate-900 text-white' : 'hover:bg-slate-100'} ${u.is_active ? '' : 'opacity-60'}`}
                                    >
                                        {u.name}
                                        <span className="ml-2 text-xs opacity-70">{u.code} · {u.users_count}</span>
                                    </Link>
                                </li>
                            ))}
                        </ul>
                        {can.create && units.length === 0 && (
                            <div className="p-2"><AddUnit types={types} errors={errors} onSubmit={onCreateUnit} /></div>
                        )}
                    </nav>

                    {selected && (
                        <div className="space-y-6 rounded-lg bg-white p-4 shadow-sm">
                            <div>
                                <p className="text-xs text-slate-500">{selected.path.length > 0 ? selected.path.join(' / ') : t('org.root')}</p>
                                <h2 className="text-lg font-semibold">{selected.name}</h2>
                                <p className="text-sm text-slate-500">
                                    {t('org.summary', { people: selected.users_count, children: selected.children_count })}
                                </p>
                            </div>

                            {can.update
                                ? <UnitForm key={selected.id} unit={selected} types={types} errors={errors} onSubmit={onUpdateUnit} />
                                : (
                                    <dl className="grid gap-2 text-sm sm:grid-cols-2">
                                        <div><dt className="text-slate-500">{t('org.code')}</dt><dd>{selected.code}</dd></div>
                                        <div><dt className="text-slate-500">{t('org.type')}</dt><dd>{t(`org.types.${selected.type}`)}</dd></div>
                                        <div><dt className="text-slate-500">{t('org.head')}</dt><dd>{selected.members.find((m) => m.id === selected.head_user_id)?.name ?? t('org.noHead')}</dd></div>
                                    </dl>
                                )}

                            <Positions key={`p${selected.id}`} unit={selected} errors={errors} canEdit={can.update} onSave={onSavePosition} onDelete={onDeletePosition} />

                            {can.create && <AddUnit key={`a${selected.id}`} parent={selected} types={types} errors={errors} onSubmit={onCreateUnit} />}

                            {can.update && selected.parent_id !== null && (
                                <form onSubmit={(e) => { e.preventDefault(); onMoveUnit?.(parentId || null); }} className="flex flex-wrap items-end gap-2">
                                    <SelectField
                                        label={t('org.moveTo')}
                                        value={parentId}
                                        onChange={(e) => setParentId(e.target.value)}
                                        error={errors.parent_id}
                                        placeholder=""
                                        options={units.filter((u) => !invalidParents.has(u.id)).map((u) => ({ value: u.id, label: indent(u) }))}
                                    />
                                    <button className={button} disabled={!parentId}>{t('org.move')}</button>
                                </form>
                            )}

                            {can.delete && (
                                confirmDelete ? (
                                    <div role="alertdialog" aria-label={t('org.delete')} className="flex flex-wrap items-center gap-2 rounded bg-red-50 p-3 text-sm">
                                        <span>{t('org.deleteConfirm', { name: selected.name })}</span>
                                        <button type="button" className="rounded bg-red-700 px-3 py-1 text-white" onClick={() => { onDeleteUnit?.(); setConfirmDelete(false); }}>{t('org.confirm')}</button>
                                        <button type="button" className={button} onClick={() => setConfirmDelete(false)}>{t('org.cancel')}</button>
                                    </div>
                                ) : (
                                    <button type="button" className={`${button} text-red-700`} onClick={() => setConfirmDelete(true)}>{t('org.deleteUnit')}</button>
                                )
                            )}
                        </div>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
