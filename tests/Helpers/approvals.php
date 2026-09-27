<?php

// Shared by the approval tests.

use App\Domain\Approvals\Actions\SubmitRequest;
use App\Domain\Approvals\Models\ApprovalRequest;
use App\Domain\Approvals\Models\ApprovalWorkflow;
use App\Domain\Identity\Models\User;
use App\Domain\Organization\Enums\OrgUnitType;
use App\Domain\Organization\Models\OrgUnit;
use Database\Seeders\AccessSeeder;
use Database\Seeders\ApprovalSeeder;

function seed_approvals(): void
{
    test()->seed([AccessSeeder::class, ApprovalSeeder::class]);
}

function user_with(string $role, array $attrs = []): User
{
    $user = User::factory()->create($attrs);
    $user->assignRole($role);

    return $user;
}

/**
 * HQ > DIV > UNIT, each with a head, plus the people the seeded workflows need:
 * $head runs UNIT, $divHead runs DIV, $hr is an HR officer, $admin a super admin, $target is staff in UNIT.
 *
 * @return array<string, mixed>
 */
function approval_world(): array
{
    $hq = OrgUnit::factory()->create(['code' => 'HQ', 'name' => 'HQ', 'type' => OrgUnitType::Headquarters]);
    $div = OrgUnit::factory()->create(['code' => 'DIV', 'name' => 'Division', 'type' => OrgUnitType::Division, 'parent_id' => $hq->id]);
    $unit = OrgUnit::factory()->create(['code' => 'UNIT', 'name' => 'Unit', 'type' => OrgUnitType::Unit, 'parent_id' => $div->id]);

    $head = user_with('dept_head', ['org_unit_id' => $unit->id, 'name' => 'Unit Head']);
    $divHead = user_with('dept_head', ['org_unit_id' => $div->id, 'name' => 'Division Head']);
    $unit->update(['head_user_id' => $head->id]);
    $div->update(['head_user_id' => $divHead->id]);

    return [
        'hq' => $hq, 'div' => $div, 'unit' => $unit, 'head' => $head, 'divHead' => $divHead,
        'hr' => user_with('hr_officer', ['org_unit_id' => $hq->id, 'name' => 'HR']),
        'admin' => user_with('super_admin', ['org_unit_id' => $hq->id, 'name' => 'Admin']),
        'target' => user_with('staff', ['org_unit_id' => $unit->id, 'name' => 'Target']),
    ];
}

function workflow(string $code): ApprovalWorkflow
{
    return ApprovalWorkflow::where('code', $code)->firstOrFail();
}

/** @param array<string, mixed> $payload */
function submit(User $requester, string $code, array $payload, ?string $justification = 'Please'): ApprovalRequest
{
    return app(SubmitRequest::class)($requester, workflow($code), $payload, $justification);
}
