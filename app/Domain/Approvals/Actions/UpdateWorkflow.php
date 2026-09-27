<?php

namespace App\Domain\Approvals\Actions;

use App\Domain\Approvals\Models\ApprovalWorkflow;
use App\Domain\Audit\Audit;

/** Turns a workflow on/off, and whether connected apps may submit to it. Never touches its steps. */
class UpdateWorkflow
{
    /** @param array{is_active?: bool, allow_api?: bool} $data */
    public function __invoke(ApprovalWorkflow $workflow, array $data): ApprovalWorkflow
    {
        $before = $workflow->only(['is_active', 'allow_api']);
        $workflow->update($data);

        [$old, $new] = Audit::diff($before, $workflow->only(['is_active', 'allow_api']));
        if ($new !== []) {
            Audit::record('UPDATE', "Updated workflow {$workflow->code}", $workflow, $old, $new);
        }

        return $workflow;
    }
}
