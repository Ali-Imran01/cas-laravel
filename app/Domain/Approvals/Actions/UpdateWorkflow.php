<?php

namespace App\Domain\Approvals\Actions;

use App\Domain\Approvals\Handlers\HandlerRegistry;
use App\Domain\Approvals\Models\ApprovalWorkflow;
use App\Domain\Audit\Audit;
use Illuminate\Validation\ValidationException;

/** Turns a workflow on/off, and whether connected apps may submit to it. Never touches its steps. */
class UpdateWorkflow
{
    public function __construct(private readonly HandlerRegistry $handlers) {}

    /** @param array{is_active?: bool, allow_api?: bool} $data
     *
     * @throws ValidationException
     */
    public function __invoke(ApprovalWorkflow $workflow, array $data): ApprovalWorkflow
    {
        // A built-in workflow puts real administrative power behind its last approval; a connected app never gets to trigger that.
        if (($data['allow_api'] ?? $workflow->allow_api) && $this->handlers->for($workflow->code) !== null) {
            throw ValidationException::withMessages(['allow_api' => __('cas.approvals.builtin_no_api')]);
        }

        $before = $workflow->only(['is_active', 'allow_api']);
        $workflow->update($data);

        [$old, $new] = Audit::diff($before, $workflow->only(['is_active', 'allow_api']));
        if ($new !== []) {
            Audit::record('UPDATE', "Updated workflow {$workflow->code}", $workflow, $old, $new);
        }

        return $workflow;
    }
}
