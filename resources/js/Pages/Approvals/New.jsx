import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import AppLayout from '../../Components/AppLayout';
import Field from '../../Components/Field';
import FlashMessages from '../../Components/FlashMessages';
import { useLink } from '../../Components/LinkContext';
import SelectField from '../../Components/SelectField';

const primary = 'rounded bg-slate-900 px-3 py-1.5 text-sm text-white disabled:opacity-60';
const userOptions = (users) => users.map((u) => ({ value: u.id, label: `${u.staff_id} — ${u.name}` }));

function RoleChangeFields({ v, set, errors, users, roles }) {
    const { t } = useTranslation();
    return (
        <>
            <SelectField label={t('approvalsNew.fields.user')} value={v.user_id ?? ''} onChange={set('user_id')} error={errors['payload.user_id']} placeholder="" options={userOptions(users)} />
            <SelectField label={t('approvalsNew.fields.toRole')} value={v.to_role ?? ''} onChange={set('to_role')} error={errors['payload.to_role']} placeholder="" options={roles.map((r) => ({ value: r.name, label: r.display_name }))} />
        </>
    );
}

function NewAccountFields({ v, set, errors, roles, orgUnits, positions }) {
    const { t } = useTranslation();
    const unitPositions = positions.filter((p) => String(p.org_unit_id) === String(v.org_unit_id));
    return (
        <>
            <Field label={t('approvalsNew.fields.staffId')} value={v.staff_id ?? ''} onChange={set('staff_id')} error={errors['payload.staff_id']} placeholder="STF-10010" />
            <Field label={t('approvalsNew.fields.name')} value={v.name ?? ''} onChange={set('name')} error={errors['payload.name']} />
            <Field label={t('approvalsNew.fields.email')} type="email" value={v.email ?? ''} onChange={set('email')} error={errors['payload.email']} />
            <SelectField label={t('approvalsNew.fields.role')} value={v.role ?? ''} onChange={set('role')} error={errors['payload.role']} placeholder={t('approvalsNew.fields.noRole')} options={roles.map((r) => ({ value: r.name, label: r.display_name }))} />
            <SelectField label={t('approvalsNew.fields.orgUnit')} value={v.org_unit_id ?? ''} onChange={set('org_unit_id')} error={errors['payload.org_unit_id']} placeholder={t('approvalsNew.fields.noUnit')} options={orgUnits.map((u) => ({ value: u.id, label: u.name }))} />
            <SelectField label={t('approvalsNew.fields.position')} value={v.position_id ?? ''} onChange={set('position_id')} error={errors['payload.position_id']} placeholder={t('approvalsNew.fields.noPosition')} options={unitPositions.map((p) => ({ value: p.id, label: p.title }))} />
        </>
    );
}

function AppAccessFields({ v, set, errors, users, applications }) {
    const { t } = useTranslation();
    return (
        <>
            <SelectField label={t('approvalsNew.fields.application')} value={v.application_id ?? ''} onChange={set('application_id')} error={errors['payload.application_id']} placeholder="" options={applications.map((a) => ({ value: a.id, label: a.name }))} />
            <SelectField label={t('approvalsNew.fields.user')} value={v.user_id ?? ''} onChange={set('user_id')} error={errors['payload.user_id']} placeholder="" options={userOptions(users)} />
            <Field label={t('approvalsNew.fields.appRole')} value={v.app_role ?? ''} onChange={set('app_role')} error={errors['payload.app_role']} />
            <Field label={t('approvalsNew.fields.expires')} type="date" value={v.expires_at ?? ''} onChange={set('expires_at')} error={errors['payload.expires_at']} />
        </>
    );
}

function ReactivationFields({ v, set, errors, users }) {
    const { t } = useTranslation();
    return <SelectField label={t('approvalsNew.fields.user')} value={v.user_id ?? ''} onChange={set('user_id')} error={errors['payload.user_id']} placeholder="" options={userOptions(users.filter((u) => u.status === 'inactive'))} />;
}

function TransferFields({ v, set, errors, users, orgUnits, positions }) {
    const { t } = useTranslation();
    const unitPositions = positions.filter((p) => String(p.org_unit_id) === String(v.org_unit_id));
    return (
        <>
            <SelectField label={t('approvalsNew.fields.user')} value={v.user_id ?? ''} onChange={set('user_id')} error={errors['payload.user_id']} placeholder="" options={userOptions(users)} />
            <SelectField label={t('approvalsNew.fields.orgUnit')} value={v.org_unit_id ?? ''} onChange={set('org_unit_id')} error={errors['payload.org_unit_id']} placeholder="" options={orgUnits.map((u) => ({ value: u.id, label: u.name }))} />
            <SelectField label={t('approvalsNew.fields.position')} value={v.position_id ?? ''} onChange={set('position_id')} error={errors['payload.position_id']} placeholder={t('approvalsNew.fields.noPosition')} options={unitPositions.map((p) => ({ value: p.id, label: p.title }))} />
            <Field label={t('approvalsNew.fields.effective')} type="date" value={v.effective ?? ''} onChange={set('effective')} error={errors['payload.effective']} />
        </>
    );
}

function NewRoleFields({ v, set, errors, permissions }) {
    const { t } = useTranslation();
    const selected = v.permissions ?? [];
    const toggle = (name) => set('permissions')({ target: { value: selected.includes(name) ? selected.filter((x) => x !== name) : [...selected, name] } });

    return (
        <>
            <Field label={t('approvalsNew.fields.displayName')} value={v.display_name ?? ''} onChange={set('display_name')} error={errors['payload.display_name']} />
            <Field label={t('approvalsNew.fields.name')} value={v.name ?? ''} onChange={set('name')} error={errors['payload.name']} placeholder="service_desk_agent" />
            <Field label={t('approvalsNew.fields.description')} value={v.description ?? ''} onChange={set('description')} error={errors['payload.description']} />
            <fieldset className="max-h-48 space-y-1 overflow-y-auto rounded border p-2 text-sm">
                <legend className="px-1">{t('approvalsNew.fields.permissions')}</legend>
                {permissions.map((name) => (
                    <label key={name} className="flex items-center gap-2">
                        <input type="checkbox" checked={selected.includes(name)} onChange={() => toggle(name)} />
                        <code>{name}</code>
                    </label>
                ))}
            </fieldset>
            {errors['payload.permissions'] && <p role="alert" className="text-sm text-red-700">{errors['payload.permissions']}</p>}
        </>
    );
}

const WORKFLOW_FIELDS = {
    role_change: RoleChangeFields, new_account: NewAccountFields, app_access: AppAccessFields,
    reactivation: ReactivationFields, transfer: TransferFields, new_role: NewRoleFields,
};

// Dumb page: onSubmit comes from the entry point and posts {workflow, justification, payload}.
export default function New({ workflows = [], users = [], roles = [], orgUnits = [], positions = [], applications = [], permissions = [], errors = {}, flash = {}, onSubmit, onSignOut }) {
    const { t } = useTranslation();
    const Link = useLink();
    const [code, setCode] = useState(workflows.find((w) => w.requestable)?.code ?? workflows[0]?.code ?? '');
    const [justification, setJustification] = useState('');
    const [payload, setPayload] = useState({});
    const set = (field) => (e) => setPayload((p) => ({ ...p, [field]: e.target.value }));

    const chosen = workflows.find((w) => w.code === code);
    const Fields = WORKFLOW_FIELDS[code];

    return (
        <AppLayout current="approvals" onSignOut={onSignOut}>
            <div className="mx-auto max-w-xl space-y-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-xl font-semibold">{t('approvalsNew.title')}</h1>
                    <Link href="/approvals" className="text-sm underline">{t('approvalsNew.back')}</Link>
                </div>
                <FlashMessages flash={flash} errors={errors} />

                <form
                    onSubmit={(e) => { e.preventDefault(); onSubmit?.({ workflow: code, justification: justification || null, payload }); }}
                    className="space-y-4 rounded-lg bg-white p-4 shadow-sm"
                >
                    <SelectField
                        label={t('approvalsNew.pickWorkflow')}
                        value={code}
                        onChange={(e) => { setCode(e.target.value); setPayload({}); }}
                        placeholder=""
                        options={workflows.map((w) => ({ value: w.code, label: w.name }))}
                    />
                    {chosen && !chosen.requestable && <p className="text-sm text-amber-800">{t('approvalsNew.notRequestable')}</p>}

                    {Fields && <Fields v={payload} set={set} errors={errors} users={users} roles={roles} orgUnits={orgUnits} positions={positions} applications={applications} permissions={permissions} />}

                    <div className="text-sm">
                        <label htmlFor="justification">{t('approvalsNew.justification')}</label>
                        <textarea id="justification" rows={3} value={justification} onChange={(e) => setJustification(e.target.value)} className="mt-1 w-full rounded border px-3 py-2" />
                    </div>

                    <button className={primary} disabled={!chosen?.requestable}>{t('approvalsNew.submit')}</button>
                </form>
            </div>
        </AppLayout>
    );
}
