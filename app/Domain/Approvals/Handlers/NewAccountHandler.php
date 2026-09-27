<?php

namespace App\Domain\Approvals\Handlers;

use App\Domain\Access\Actions\AssignableRoles;
use App\Domain\Approvals\Models\ApprovalRequest;
use App\Domain\Identity\Actions\CreateUser;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Rules\UserRules;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

/** payload: {staff_id, name, email, role?, org_unit_id?, position_id?} */
class NewAccountHandler implements WorkflowHandler
{
    public function __construct(private readonly CreateUser $create, private readonly AssignableRoles $assignable) {}

    public function code(): string
    {
        return 'new_account';
    }

    public function rules(User $requester, array $payload): array
    {
        // Any existing role may be asked for; whether the approver can actually grant it is checked when they approve.
        return UserRules::base(null, Role::query()->pluck('name')->all()) + [
            'org_unit_id' => ['nullable', 'integer', Rule::exists('org_units', 'id')->whereNull('deleted_at')],
            'position_id' => ['nullable', 'integer', Rule::exists('positions', 'id')->where('org_unit_id', $payload['org_unit_id'] ?? null)],
        ];
    }

    public function normalize(array $payload): array
    {
        return [
            'staff_id' => strtoupper(trim((string) ($payload['staff_id'] ?? ''))),
            'name' => trim((string) ($payload['name'] ?? '')),
            'email' => strtolower(trim((string) ($payload['email'] ?? ''))),
            'role' => ($payload['role'] ?? null) ?: null,
            'org_unit_id' => ($payload['org_unit_id'] ?? null) ?: null,
            'position_id' => ($payload['position_id'] ?? null) ?: null,
        ];
    }

    public function canRequest(User $requester, array $payload): bool
    {
        return $requester->checkPermissionTo('users.view');
    }

    public function subject(array $payload): array
    {
        return [User::class, null];
    }

    public function describe(array $payload): string
    {
        return "Create account {$payload['staff_id']} ({$payload['name']})";
    }

    public function canApply(User $approver, array $payload): bool
    {
        return $approver->can('create', User::class)
            && ($payload['role'] === null || ($this->assignable)($approver)->contains('name', $payload['role']));
    }

    public function apply(User $approver, ApprovalRequest $request): void
    {
        ($this->create)($approver, $request->payload);
    }
}
