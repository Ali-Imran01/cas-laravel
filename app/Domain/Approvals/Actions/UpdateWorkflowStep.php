<?php

namespace App\Domain\Approvals\Actions;

use App\Domain\Approvals\Enums\ApproverType;
use App\Domain\Approvals\Models\ApprovalWorkflowStep;
use App\Domain\Audit\Audit;

/** Changes who approves a step and how long they have. The level and name it belongs to never change here. */
class UpdateWorkflowStep
{
    /** @param array{approver_type: string, approver_role_id: int|null, approver_user_id: int|null, sla_hours: int} $data */
    public function __invoke(ApprovalWorkflowStep $step, array $data): ApprovalWorkflowStep
    {
        $type = ApproverType::from($data['approver_type']);
        $before = ['approver_type' => $step->approver_type->value, 'approver_role_id' => $step->approver_role_id, 'approver_user_id' => $step->approver_user_id, 'sla_hours' => $step->sla_hours];

        $step->update([
            'approver_type' => $type,
            'approver_role_id' => $type === ApproverType::Role ? $data['approver_role_id'] : null,
            'approver_user_id' => $type === ApproverType::User ? $data['approver_user_id'] : null,
            'sla_hours' => $data['sla_hours'],
        ]);

        [$old, $new] = Audit::diff($before, ['approver_type' => $step->approver_type->value, 'approver_role_id' => $step->approver_role_id, 'approver_user_id' => $step->approver_user_id, 'sla_hours' => $step->sla_hours]);
        if ($new !== []) {
            Audit::record('UPDATE', "Updated step \"{$step->name}\" of workflow #{$step->workflow_id}", $step, $old, $new);
        }

        return $step;
    }
}
