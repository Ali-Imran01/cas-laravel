<?php

namespace App\Http\Controllers\Approvals;

use App\Domain\Approvals\Actions\UpdateWorkflow;
use App\Domain\Approvals\Actions\UpdateWorkflowStep;
use App\Domain\Approvals\Enums\ApproverType;
use App\Domain\Approvals\Models\ApprovalWorkflow;
use App\Domain\Approvals\Models\ApprovalWorkflowStep;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class ApprovalWorkflowController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', ApprovalWorkflow::class);

        $workflows = ApprovalWorkflow::query()->with('steps')->orderBy('name')->get()->map(fn (ApprovalWorkflow $w) => [
            'id' => $w->id,
            'code' => $w->code,
            'name' => $w->name,
            'is_active' => $w->is_active,
            'allow_api' => $w->allow_api,
            'steps' => $w->steps->map(fn (ApprovalWorkflowStep $s) => [
                'id' => $s->id, 'level' => $s->level, 'name' => $s->name, 'approver_type' => $s->approver_type->value,
                'approver_role_id' => $s->approver_role_id, 'approver_user_id' => $s->approver_user_id, 'sla_hours' => $s->sla_hours,
            ])->all(),
        ])->all();

        return Inertia::render('Approvals/Workflows', [
            'workflows' => $workflows,
            'roles' => Role::query()->orderBy('name')->get(['id', 'name', 'display_name'])->map(fn (Role $r) => ['id' => $r->id, 'name' => $r->name, 'display_name' => $r->getAttribute('display_name')])->all(),
        ]);
    }

    public function update(Request $request, ApprovalWorkflow $workflow, UpdateWorkflow $updateWorkflow): RedirectResponse
    {
        $this->authorize('update', $workflow);
        $data = $request->validate(['is_active' => ['required', 'boolean'], 'allow_api' => ['required', 'boolean']]);

        $updateWorkflow($workflow, $data);

        return back()->with('status', __('cas.approvals.workflow_updated'));
    }

    public function updateStep(Request $request, ApprovalWorkflow $workflow, ApprovalWorkflowStep $step, UpdateWorkflowStep $updateStep): RedirectResponse
    {
        $this->authorize('update', $workflow);
        abort_unless($step->workflow_id === $workflow->id, 404);

        $data = $request->validate([
            'approver_type' => ['required', Rule::enum(ApproverType::class)],
            'approver_role_id' => ['required_if:approver_type,role', 'nullable', 'integer', 'exists:roles,id'],
            'approver_user_id' => ['required_if:approver_type,user', 'nullable', 'integer', 'exists:users,id'],
            'sla_hours' => ['required', 'integer', 'min:1', 'max:720'],
        ]);

        $updateStep($step, $data);

        return back()->with('status', __('cas.approvals.step_updated'));
    }
}
