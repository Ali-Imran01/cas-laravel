<?php

namespace App\Domain\Approvals\Handlers;

use App\Domain\Approvals\Models\ApprovalRequest;
use App\Domain\Identity\Actions\ChangeUserStatus;
use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Models\User;
use Illuminate\Validation\Rule;

/** payload: {user_id} of a deactivated account */
class ReactivationHandler implements WorkflowHandler
{
    public function __construct(private readonly ChangeUserStatus $status) {}

    public function code(): string
    {
        return 'reactivation';
    }

    public function rules(User $requester, array $payload): array
    {
        return ['user_id' => ['required', 'integer', Rule::exists('users', 'id')->whereNull('deleted_at')->where('status', UserStatus::Inactive->value)]];
    }

    public function normalize(array $payload): array
    {
        return ['user_id' => (int) ($payload['user_id'] ?? 0)];
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
        return 'Reactivate '.(User::query()->whereKey($payload['user_id'])->value('staff_id') ?? '#'.$payload['user_id']);
    }

    public function canApply(User $approver, array $payload): bool
    {
        $target = User::query()->find($payload['user_id']);

        return $target !== null && $approver->can('reactivate', $target);
    }

    public function apply(User $approver, ApprovalRequest $request): void
    {
        ($this->status)($approver, User::query()->findOrFail($request->payload['user_id']), UserStatus::Active);
    }
}
