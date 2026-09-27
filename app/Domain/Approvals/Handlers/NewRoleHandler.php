<?php

namespace App\Domain\Approvals\Handlers;

use App\Domain\Access\Actions\GrantablePermissions;
use App\Domain\Approvals\Models\ApprovalRequest;
use App\Domain\Audit\Audit;
use App\Domain\Identity\Models\User;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

/** payload: {name, display_name, description?, permissions[]} */
class NewRoleHandler implements WorkflowHandler
{
    public function __construct(private readonly GrantablePermissions $grantable) {}

    public function code(): string
    {
        return 'new_role';
    }

    public function rules(User $requester, array $payload): array
    {
        return [
            'name' => ['required', 'string', 'regex:/^[a-z][a-z0-9_]{1,48}$/', Rule::unique('roles', 'name')->where('guard_name', 'web')],
            'display_name' => ['required', 'string', 'max:125'],
            'description' => ['nullable', 'string', 'max:255'],
            'permissions' => ['present', 'array'],
            'permissions.*' => ['string', 'distinct', Rule::exists('permissions', 'name')->where('guard_name', 'web')],
        ];
    }

    public function normalize(array $payload): array
    {
        return [
            'name' => (string) ($payload['name'] ?? ''),
            'display_name' => trim((string) ($payload['display_name'] ?? '')),
            'description' => ($payload['description'] ?? null) ?: null,
            'permissions' => array_values((array) ($payload['permissions'] ?? [])),
        ];
    }

    public function canRequest(User $requester, array $payload): bool
    {
        return $requester->checkPermissionTo('roles.view');
    }

    public function subject(array $payload): array
    {
        return [Role::class, null];
    }

    public function describe(array $payload): string
    {
        return "Create role {$payload['name']} with ".count($payload['permissions']).' permissions';
    }

    /** The approver hands out these permissions, so they must hold every one themselves. */
    public function canApply(User $approver, array $payload): bool
    {
        return $approver->can('create', Role::class) && array_diff($payload['permissions'], ($this->grantable)($approver)->all()) === [];
    }

    public function apply(User $approver, ApprovalRequest $request): void
    {
        $p = $request->payload;
        $role = Role::query()->create(['name' => $p['name'], 'display_name' => $p['display_name'], 'description' => $p['description'] ?? null, 'guard_name' => 'web', 'is_system' => false]);
        $role->syncPermissions($p['permissions']);

        Audit::record('CREATE', "Created role {$role->name} (approved request {$request->reference})", $role, [], $p);
    }
}
