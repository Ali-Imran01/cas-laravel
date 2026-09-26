<?php

namespace Database\Seeders;

use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Models\User;
use App\Domain\Organization\Enums\OrgUnitType;
use App\Domain\Organization\Models\OrgUnit;
use App\Domain\Organization\Models\Position;
use Illuminate\Database\Seeder;

/**
 * Demo data. Roles are attached in Phase 2 (access), so the demo accounts differ only by org placement for now.
 * All demo accounts use the password "password".
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $hq = OrgUnit::create(['type' => OrgUnitType::Headquarters, 'code' => 'HQ', 'name' => 'Headquarters']);
        $ict = OrgUnit::create(['type' => OrgUnitType::Division, 'code' => 'ICT', 'name' => 'ICT Division', 'parent_id' => $hq->id]);
        $apd = OrgUnit::create(['type' => OrgUnitType::Unit, 'code' => 'ICT-APD', 'name' => 'Application Development Unit', 'parent_id' => $ict->id]);

        $head = Position::create(['org_unit_id' => $ict->id, 'title' => 'Head of Division', 'grade' => 'F52', 'headcount' => 1]);
        $analyst = Position::create(['org_unit_id' => $apd->id, 'title' => 'Systems Analyst', 'grade' => 'F41', 'headcount' => 4]);

        $accounts = [
            ['STF-10001', 'Aisyah Rahman', 'superadmin@cas.demo', $hq, null, true],
            ['STF-10002', 'Hafiz Ismail', 'hr.officer@cas.demo', $hq, null, true],
            ['STF-10003', 'Nurul Huda', 'dept.head@cas.demo', $ict, $head, false],
            ['STF-10004', 'Daniel Lee', 'staff@cas.demo', $apd, $analyst, false],
        ];

        $users = [];
        foreach ($accounts as [$staffId, $name, $email, $unit, $position, $mfa]) {
            $users[$email] = User::factory()->create([
                'staff_id' => $staffId,
                'name' => $name,
                'email' => $email,
                'org_unit_id' => $unit->id,
                'position_id' => $position?->id,
            ]);
            $users[$email]->forceFill(['mfa_enabled' => $mfa])->save();
        }

        User::factory()->create([
            'staff_id' => 'STF-10005',
            'name' => 'Siti Aminah',
            'email' => 'siti@cas.demo',
            'org_unit_id' => $apd->id,
            'position_id' => $analyst->id,
            'status' => UserStatus::Locked,
        ]);

        $hq->update(['head_user_id' => $users['superadmin@cas.demo']->id]);
        $ict->update(['head_user_id' => $users['dept.head@cas.demo']->id]);
        $apd->update(['head_user_id' => $users['dept.head@cas.demo']->id]);
    }
}
