<?php

namespace App\Http\Controllers\Approvals;

use App\Domain\Approvals\Actions\DecideRequest;
use App\Domain\Approvals\Enums\ApprovalStatus;
use App\Domain\Approvals\Handlers\HandlerRegistry;
use App\Domain\Approvals\Models\ApprovalRequest;
use App\Domain\Identity\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ApprovalController extends Controller
{
    public function __construct(private readonly DecideRequest $decide, private readonly HandlerRegistry $handlers) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', ApprovalRequest::class);
        $user = $request->user();
        $canOversee = $user->checkPermissionTo('approvals.view');

        $filters = $request->validate([
            'tab' => ['nullable', 'in:decide,mine,all'],
            'request' => ['nullable', 'integer'],
        ]);
        $requested = $filters['tab'] ?? 'decide';
        $tab = ($requested === 'all' && ! $canOversee) ? 'decide' : $requested;

        $base = ApprovalRequest::query()->with(['workflow', 'requester']);
        $rows = match ($tab) {
            'mine' => (clone $base)->where('requester_id', $user->id)->latest('id')->get(),
            'all' => (clone $base)->latest('id')->get(),
            default => (clone $base)->where('status', ApprovalStatus::Pending)->get()->filter(fn (ApprovalRequest $r) => $this->decide->canDecide($user, $r))->values(),
        };

        $list = $rows->map(fn (ApprovalRequest $r) => $this->row($r))->all();

        $selectedId = (int) ($filters['request'] ?? ($list[0]['id'] ?? 0));
        $selected = ApprovalRequest::with(['workflow', 'requester', 'sourceApplication', 'actions.actor'])->find($selectedId);

        return Inertia::render('Approvals/Index', [
            'requests' => $list,
            'tab' => $tab,
            'selected' => ($selected && $user->can('view', $selected)) ? $this->detail($selected, $user) : null,
            'can' => [
                'oversee' => $canOversee,
                'submit' => true, // approvals are self-service; kept as a flag so the read-only demo can hide the form
                'manageWorkflows' => $user->checkPermissionTo('approvals.edit'),
            ],
        ]);
    }

    /** @return array<string, mixed> */
    private function row(ApprovalRequest $r): array
    {
        return [
            'id' => $r->id,
            'reference' => $r->reference,
            'workflow' => $r->workflow->name,
            'requester' => $r->requester->name,
            'status' => $r->status->value,
            'current_level' => $r->current_level,
            'levels' => count($r->steps),
            'due_at' => $r->due_at?->toIso8601String(),
            'overdue' => $r->isOverdue(),
            'summary' => $this->summary($r),
            'created_at' => $r->created_at->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    private function detail(ApprovalRequest $r, User $user): array
    {
        return [
            'id' => $r->id,
            'reference' => $r->reference,
            'workflow' => $r->workflow->name,
            'workflow_code' => $r->workflow->code,
            'requester' => ['id' => $r->requester->id, 'name' => $r->requester->name, 'staff_id' => $r->requester->staff_id],
            'status' => $r->status->value,
            'current_level' => $r->current_level,
            'steps' => $r->steps,
            'justification' => $r->justification,
            'payload' => $r->payload,
            'summary' => $this->summary($r),
            'source_application' => $r->sourceApplication?->name,
            'due_at' => $r->due_at?->toIso8601String(),
            'overdue' => $r->isOverdue(),
            'completed_at' => $r->completed_at?->toIso8601String(),
            'created_at' => $r->created_at->toIso8601String(),
            'actions' => $r->actions->map(fn ($a) => [
                'id' => $a->id, 'level' => $a->level, 'decision' => $a->decision->value,
                'actor' => $a->actor?->name, 'comment' => $a->comment, 'created_at' => $a->created_at->toIso8601String(),
            ])->all(),
            'can' => [
                'decide' => $this->decide->canDecide($user, $r),
                'requester' => $r->requester_id === $user->id,
                'comment' => $r->status->isOpen() && ($r->requester_id === $user->id || $this->decide->canDecide($user, $r)),
            ],
        ];
    }

    private function summary(ApprovalRequest $r): string
    {
        return $this->handlers->for($r->workflow->code)?->describe($r->payload) ?? $r->workflow->name;
    }
}
