<?php

namespace App\Http\Controllers\Approvals;

use App\Domain\Approvals\Actions\SubmitRequest;
use App\Domain\Approvals\Handlers\HandlerRegistry;
use App\Domain\Approvals\Models\ApprovalRequest;
use App\Domain\Approvals\Models\ApprovalWorkflow;
use App\Domain\Apps\Models\Application;
use App\Domain\Identity\Models\User;
use App\Domain\Organization\Models\OrgUnit;
use App\Domain\Organization\Models\Position;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class ApprovalSubmitController extends Controller
{
    // Roughly what each built-in workflow needs to even be attempted; the handler checks the real rule at submit time.
    private const REQUEST_PERMISSION = [
        'role_change' => 'users.view',
        'new_account' => 'users.view',
        'reactivation' => 'users.view',
        'transfer' => 'users.view',
        'new_role' => 'roles.view',
    ];

    public function __construct(private readonly HandlerRegistry $handlers) {}

    public function create(Request $request): Response
    {
        $this->authorize('viewAny', ApprovalRequest::class);
        $user = $request->user();

        $workflows = ApprovalWorkflow::query()->whereIn('code', $this->handlers->codes())->where('is_active', true)->get()
            ->map(fn (ApprovalWorkflow $w) => [
                'code' => $w->code,
                'name' => $w->name,
                'requestable' => ! isset(self::REQUEST_PERMISSION[$w->code]) || $user->checkPermissionTo(self::REQUEST_PERMISSION[$w->code]),
            ])->all();

        return Inertia::render('Approvals/New', [
            'workflows' => $workflows,
            'users' => User::query()->orderBy('name')->get(['id', 'staff_id', 'name', 'status'])->map(fn (User $u) => ['id' => $u->id, 'staff_id' => $u->staff_id, 'name' => $u->name, 'status' => $u->status->value])->all(),
            'roles' => Role::query()->orderBy('name')->get(['name', 'display_name'])->map(fn (Role $r) => ['name' => $r->name, 'display_name' => $r->getAttribute('display_name')])->all(),
            'permissions' => Permission::query()->where('guard_name', 'web')->orderBy('name')->pluck('name')->all(),
            'orgUnits' => OrgUnit::query()->orderBy('name')->get(['id', 'name', 'code'])->all(),
            'positions' => Position::query()->orderBy('title')->get(['id', 'org_unit_id', 'title'])->all(),
            'applications' => Application::query()->orderBy('name')->get(['id', 'code', 'name'])->all(),
        ]);
    }

    public function store(Request $request, SubmitRequest $submit): RedirectResponse
    {
        $this->authorize('viewAny', ApprovalRequest::class);

        $data = $request->validate([
            'workflow' => ['required', 'string', Rule::in($this->handlers->codes())],
            'justification' => ['nullable', 'string', 'max:1000'],
            'payload' => ['present', 'array'],
        ]);

        // The 6 codes are always seeded; a missing row would be a deployment problem, not a validation error.
        $workflow = ApprovalWorkflow::query()->where('code', $data['workflow'])->firstOrFail();

        $created = $submit($request->user(), $workflow, $data['payload'], $data['justification'] ?? null);

        return redirect()->route('approvals.index', ['request' => $created->id])->with('status', __('cas.approvals.submitted'));
    }
}
