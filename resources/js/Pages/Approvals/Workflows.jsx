import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import AppLayout from '../../Components/AppLayout';
import FlashMessages from '../../Components/FlashMessages';
import { useLink } from '../../Components/LinkContext';
import SelectField from '../../Components/SelectField';

const button = 'rounded border px-3 py-1 text-sm';
const APPROVER_TYPES = ['role', 'user', 'unit_head', 'division_head'];

function StepRow({ workflowId, step, roles, errors, onSave }) {
    const { t } = useTranslation();
    const [v, setV] = useState({ approver_type: step.approver_type, approver_role_id: step.approver_role_id ?? '', sla_hours: step.sla_hours });
    const key = `${workflowId}.${step.id}`;

    return (
        <form onSubmit={(e) => { e.preventDefault(); onSave(step.id, v); }} className="grid items-end gap-2 border-t py-3 sm:grid-cols-[1fr_1fr_8rem_auto]">
            <div className="text-sm font-medium sm:col-span-4">{t('approvalsWorkflows.step', { level: step.level, name: step.name })}</div>
            <SelectField
                label={t('approvalsWorkflows.approverType')}
                value={v.approver_type}
                onChange={(e) => setV({ ...v, approver_type: e.target.value })}
                options={APPROVER_TYPES.map((x) => ({ value: x, label: t(`approvalsWorkflows.types.${x}`) }))}
            />
            {v.approver_type === 'role' && (
                <SelectField
                    label={t('approvalsWorkflows.role')}
                    value={v.approver_role_id}
                    onChange={(e) => setV({ ...v, approver_role_id: e.target.value })}
                    error={errors[`${key}.approver_role_id`]}
                    placeholder=""
                    options={roles.map((r) => ({ value: r.id, label: r.display_name }))}
                />
            )}
            <div className="text-sm">
                <label htmlFor={`${key}-sla`}>{t('approvalsWorkflows.slaHours')}</label>
                <input
                    id={`${key}-sla`}
                    type="number"
                    min={1}
                    max={720}
                    value={v.sla_hours}
                    onChange={(e) => setV({ ...v, sla_hours: e.target.value })}
                    className="mt-1 w-full rounded border px-3 py-2"
                />
            </div>
            <button className={button}>{t('approvalsWorkflows.saveStep')}</button>
        </form>
    );
}

function WorkflowCard({ workflow, roles, errors, onToggle, onSaveStep }) {
    const { t } = useTranslation();

    return (
        <div className="rounded-lg bg-white p-4 shadow-sm">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <h2 className="text-lg font-semibold">{workflow.name}</h2>
                <div className="flex flex-wrap items-center gap-4 text-sm">
                    <label className="flex items-center gap-2">
                        <input type="checkbox" checked={workflow.is_active} onChange={(e) => onToggle({ is_active: e.target.checked, allow_api: workflow.allow_api })} />
                        {t('approvalsWorkflows.active')}
                    </label>
                    <label className={`flex items-center gap-2 ${workflow.has_handler ? 'text-slate-400' : ''}`} title={workflow.has_handler ? t('approvalsWorkflows.builtinNoApi') : undefined}>
                        <input
                            type="checkbox"
                            checked={workflow.allow_api}
                            disabled={workflow.has_handler}
                            onChange={(e) => onToggle({ is_active: workflow.is_active, allow_api: e.target.checked })}
                        />
                        {t('approvalsWorkflows.allowApi')}
                    </label>
                </div>
            </div>
            {workflow.steps.map((s) => (
                <StepRow key={s.id} workflowId={workflow.id} step={s} roles={roles} errors={errors} onSave={(stepId, data) => onSaveStep(workflow.id, stepId, data)} />
            ))}
        </div>
    );
}

// Dumb page: onUpdateWorkflow(id, data) and onUpdateStep(workflowId, stepId, data) come from the entry point.
export default function Workflows({ workflows = [], roles = [], errors = {}, flash = {}, onUpdateWorkflow, onUpdateStep, onSignOut }) {
    const { t } = useTranslation();
    const Link = useLink();

    return (
        <AppLayout current="approvals" onSignOut={onSignOut}>
            <div className="space-y-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-xl font-semibold">{t('approvalsWorkflows.title')}</h1>
                    <Link href="/approvals" className="text-sm underline">{t('approvalsNew.back')}</Link>
                </div>
                <FlashMessages flash={flash} errors={errors} />

                <div className="space-y-4">
                    {workflows.map((w) => (
                        <WorkflowCard
                            key={w.id}
                            workflow={w}
                            roles={roles}
                            errors={errors}
                            onToggle={(data) => onUpdateWorkflow?.(w.id, data)}
                            onSaveStep={onUpdateStep}
                        />
                    ))}
                </div>
            </div>
        </AppLayout>
    );
}
