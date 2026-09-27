import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import Field, { PrimaryButton } from './Field';
import SelectField from './SelectField';

const indent = (unit) => `${'— '.repeat(unit.depth)}${unit.name}`;

/**
 * Create/edit form. Creating also picks the first unit and position; on edit those are changed through
 * a transfer instead, so the assignment history stays accurate (`user` present = edit mode).
 */
export default function UserForm({ user, orgUnits, positions, roles, errors = {}, onSubmit }) {
    const { t } = useTranslation();
    const editing = Boolean(user);
    const [values, setValues] = useState({
        staff_id: user?.staff_id ?? '',
        name: user?.name ?? '',
        email: user?.email ?? '',
        role: user?.role ?? '',
        org_unit_id: '',
        position_id: '',
    });
    const set = (key) => (e) => setValues((v) => ({ ...v, [key]: e.target.value, ...(key === 'org_unit_id' && { position_id: '' }) }));

    const submit = (e) => {
        e.preventDefault();
        const { org_unit_id, position_id, ...rest } = values;
        onSubmit?.(editing ? rest : { ...rest, org_unit_id: org_unit_id || null, position_id: position_id || null });
    };

    const unitPositions = positions.filter((p) => String(p.org_unit_id) === String(values.org_unit_id));

    return (
        <form onSubmit={submit} className="max-w-lg space-y-4">
            <h1 className="text-xl font-semibold">{editing ? t('users.form.editTitle') : t('users.form.createTitle')}</h1>
            <Field label={t('users.form.staffId')} value={values.staff_id} onChange={set('staff_id')} error={errors.staff_id} maxLength={20} />
            <Field label={t('users.form.name')} value={values.name} onChange={set('name')} error={errors.name} />
            <Field label={t('users.form.email')} type="email" value={values.email} onChange={set('email')} error={errors.email} />
            <SelectField
                label={t('users.form.role')}
                value={values.role}
                onChange={set('role')}
                error={errors.role}
                placeholder={t('users.form.noRole')}
                options={roles.map((r) => ({ value: r.name, label: r.display_name }))}
            />
            {!editing && (
                <>
                    <SelectField
                        label={t('users.form.unit')}
                        value={values.org_unit_id}
                        onChange={set('org_unit_id')}
                        error={errors.org_unit_id}
                        placeholder={t('users.form.noUnit')}
                        options={orgUnits.map((u) => ({ value: u.id, label: indent(u) }))}
                    />
                    {values.org_unit_id && (
                        <SelectField
                            label={t('users.form.position')}
                            value={values.position_id}
                            onChange={set('position_id')}
                            error={errors.position_id}
                            placeholder={t('users.form.noPosition')}
                            options={unitPositions.map((p) => ({ value: p.id, label: p.title }))}
                        />
                    )}
                    <p className="text-xs text-slate-500">{t('users.form.inviteNote')}</p>
                </>
            )}
            <PrimaryButton>{editing ? t('users.form.save') : t('users.form.create')}</PrimaryButton>
        </form>
    );
}
