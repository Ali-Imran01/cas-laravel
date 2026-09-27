<?php

namespace App\Domain\Approvals\Handlers;

use App\Domain\Access\Actions\AssignableRoles;
use App\Domain\Approvals\Models\ApprovalRequest;
use App\Domain\Identity\Actions\ChangeUserRole;
use App\Domain\Identity\Models\User;
use Illuminate\Validation\Rule;

/** payload: {user_id, to_role} */
class RoleChangeHandler implements WorkflowHandler
{
    public function __construct(private readonly ChangeUserRole $changeRole, private readonly AssignableRoles $assignable) {}

    public function code(): string
    {
        return 'role_change';
    }

    public function rules(User $requester, array $payload): array
    {
        return [
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')->whereNull('deleted_at')],
            'to_role' => ['required', 'string', Rule::exists('roles', 'name')->where('guard_name', 'web')],
        ];
    }

    public function normalize(array $payload): array
    {
        return ['user_id' => (int) ($payload['user_id'] ?? 0), 'to_role' => (string) ($payload['to_role'] ?? '')];
    }

    public function canRequest(User $requester, array $payload): bool
    {
        return $requester->checkPermissionTo('users.view');
    }

    public function subject(array $payload): array
    {
        return [User::class, $payload['user_id']];
    }

    public function describe(array $payload): string
    {
        $staffId = User::query()->whereKey($payload['user_id'])->value('staff_id') ?? '#'.$payload['user_id'];

        return "Change role of $staffId to {$payload['to_role']}";
    }

    public function canApply(User $approver, array $payload): bool
    {
        return ($this->assignable)($approver)->contains('name', $payload['to_role']);
    }

    public function apply(User $approver, ApprovalRequest $request): void
    {
        ($this->changeRole)($approver, User::query()->findOrFail($request->payload['user_id']), $request->payload['to_role']);
    }
}
