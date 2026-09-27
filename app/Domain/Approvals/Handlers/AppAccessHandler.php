<?php

namespace App\Domain\Approvals\Handlers;

use App\Domain\Approvals\Models\ApprovalRequest;
use App\Domain\Apps\Actions\AppAccess;
use App\Domain\Apps\Models\Application;
use App\Domain\Identity\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/** payload: {application_id, user_id, app_role, expires_at?} */
class AppAccessHandler implements WorkflowHandler
{
    public function __construct(private readonly AppAccess $access) {}

    public function code(): string
    {
        return 'app_access';
    }

    public function rules(User $requester, array $payload): array
    {
        return [
            'application_id' => ['required', 'integer', Rule::exists('applications', 'id')],
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')->whereNull('deleted_at')],
            'app_role' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9 _.-]+$/'],
            'expires_at' => ['nullable', 'date_format:Y-m-d', 'after:today'],
        ];
    }

    public function normalize(array $payload): array
    {
        return [
            'application_id' => (int) ($payload['application_id'] ?? 0),
            'user_id' => (int) ($payload['user_id'] ?? 0),
            'app_role' => trim((string) ($payload['app_role'] ?? '')),
            'expires_at' => ($payload['expires_at'] ?? null) ?: null,
        ];
    }

    /** Anyone may ask for access for themselves; asking for someone else needs the people directory. */
    public function canRequest(User $requester, array $payload): bool
    {
        return (int) ($payload['user_id'] ?? 0) === $requester->id || $requester->checkPermissionTo('users.view');
    }

    public function subject(array $payload): array
    {
        return [Application::class, $payload['application_id']];
    }

    public function describe(array $payload): string
    {
        $staffId = User::query()->whereKey($payload['user_id'])->value('staff_id') ?? '#'.$payload['user_id'];
        $code = Application::query()->whereKey($payload['application_id'])->value('code') ?? '#'.$payload['application_id'];

        return "Grant $staffId access to $code as {$payload['app_role']}";
    }

    public function canApply(User $approver, array $payload): bool
    {
        return $approver->can('update', Application::class);
    }

    public function apply(User $approver, ApprovalRequest $request): void
    {
        $p = $request->payload;

        $this->access->grantUser(
            $approver,
            Application::query()->findOrFail($p['application_id']),
            User::query()->findOrFail($p['user_id']),
            $p['app_role'],
            isset($p['expires_at']) ? Carbon::parse($p['expires_at'])->endOfDay() : null,
        );
    }
}
