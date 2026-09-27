<?php

namespace App\Domain\Approvals\Handlers;

use App\Domain\Approvals\Models\ApprovalRequest;
use App\Domain\Identity\Actions\TransferUser;
use App\Domain\Identity\Models\User;
use App\Domain\Organization\Models\OrgUnit;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/** payload: {user_id, org_unit_id, position_id?, effective?} */
class TransferHandler implements WorkflowHandler
{
    public function __construct(private readonly TransferUser $transfer) {}

    public function code(): string
    {
        return 'transfer';
    }

    public function rules(User $requester, array $payload): array
    {
        return [
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')->whereNull('deleted_at')],
            'org_unit_id' => ['required', 'integer', Rule::exists('org_units', 'id')->whereNull('deleted_at')],
            'position_id' => ['nullable', 'integer', Rule::exists('positions', 'id')->where('org_unit_id', $payload['org_unit_id'] ?? null)],
            'effective' => ['nullable', 'date_format:Y-m-d'],
        ];
    }

    public function normalize(array $payload): array
    {
        return [
            'user_id' => (int) ($payload['user_id'] ?? 0),
            'org_unit_id' => (int) ($payload['org_unit_id'] ?? 0),
            'position_id' => ($payload['position_id'] ?? null) ? (int) $payload['position_id'] : null,
            'effective' => ($payload['effective'] ?? null) ?: null,
        ];
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
        $unit = OrgUnit::query()->whereKey($payload['org_unit_id'])->value('code') ?? '#'.$payload['org_unit_id'];

        return "Transfer $staffId to $unit";
    }

    public function canApply(User $approver, array $payload): bool
    {
        $target = User::query()->find($payload['user_id']);

        return $target !== null && $approver->can('transfer', $target);
    }

    public function apply(User $approver, ApprovalRequest $request): void
    {
        $p = $request->payload;

        $assignment = ($this->transfer)(
            User::query()->findOrFail($p['user_id']),
            $p['org_unit_id'],
            $p['position_id'] ?? null,
            isset($p['effective']) ? Carbon::parse($p['effective']) : null,
        );
        $assignment->update(['approval_request_id' => $request->id]); // the history shows which approval authorised the move
    }
}
