<?php

namespace Database\Seeders;

use App\Domain\Approvals\Models\ApprovalWorkflow;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

/**
 * The built-in workflows. Existing ones are left alone, so steps an admin has tuned are never overwritten
 * by re-running the seeder. Needs AccessSeeder first (steps point at roles).
 */
class ApprovalSeeder extends Seeder
{
    /** @var array<string, array{name: string, steps: list<array{0: string, 1: string, 2?: string}>}> code => steps of [name, type, role] */
    public const WORKFLOWS = [
        'role_change' => ['name' => 'Role change', 'steps' => [['Head of unit', 'unit_head'], ['Administrator', 'role', 'super_admin']]],
        'new_account' => ['name' => 'New account', 'steps' => [['Head of unit', 'unit_head'], ['HR officer', 'role', 'hr_officer']]],
        'app_access' => ['name' => 'App access', 'steps' => [['Head of unit', 'unit_head'], ['Administrator', 'role', 'super_admin']]],
        'reactivation' => ['name' => 'Account reactivation', 'steps' => [['Head of unit', 'unit_head'], ['HR officer', 'role', 'hr_officer']]],
        'transfer' => ['name' => 'Transfer', 'steps' => [['Current unit head', 'unit_head'], ['HR officer', 'role', 'hr_officer']]],
        'new_role' => ['name' => 'New role', 'steps' => [['Administrator', 'role', 'super_admin']]],
    ];

    public function run(): void
    {
        foreach (self::WORKFLOWS as $code => $definition) {
            $workflow = ApprovalWorkflow::query()->firstOrCreate(['code' => $code], ['name' => $definition['name'], 'is_active' => true]);

            if (! $workflow->wasRecentlyCreated) {
                continue;
            }

            foreach ($definition['steps'] as $i => $step) {
                [$name, $type] = $step;
                $role = $step[2] ?? null;

                $workflow->steps()->create([
                    'level' => $i + 1,
                    'name' => $name,
                    'approver_type' => $type,
                    'approver_role_id' => $role ? Role::findByName($role, 'web')->id : null,
                    'sla_hours' => 48,
                ]);
            }
        }
    }
}
