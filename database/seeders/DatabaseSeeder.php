<?php

namespace Database\Seeders;

use App\Domain\Identity\Actions\TransferUser;
use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Models\User;
use App\Domain\Organization\Enums\OrgUnitType;
use App\Domain\Organization\Models\OrgUnit;
use App\Domain\Organization\Models\Position;
use Illuminate\Database\Seeder;

/**
 * Demo data. All demo accounts use the password "password".
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(AccessSeeder::class);

        $hq = OrgUnit::create(['type' => OrgUnitType::Headquarters, 'code' => 'HQ', 'name' => 'Headquarters']);
        $ict = OrgUnit::create(['type' => OrgUnitType::Division, 'code' => 'ICT', 'name' => 'ICT Division', 'parent_id' => $hq->id]);
        $apd = OrgUnit::create(['type' => OrgUnitType::Unit, 'code' => 'ICT-APD', 'name' => 'Application Development Unit', 'parent_id' => $ict->id]);

        $head = Position::create(['org_unit_id' => $ict->id, 'title' => 'Head of Division', 'grade' => 'F52', 'headcount' => 1]);
        $analyst = Position::create(['org_unit_id' => $apd->id, 'title' => 'Systems Analyst', 'grade' => 'F41', 'headcount' => 4]);

        $accounts = [
            ['STF-10001', 'Aisyah Rahman', 'superadmin@cas.demo', 'super_admin', $hq, null, true, UserStatus::Active],
            ['STF-10002', 'Hafiz Ismail', 'hr.officer@cas.demo', 'hr_officer', $hq, null, true, UserStatus::Active],
            ['STF-10003', 'Nurul Huda', 'dept.head@cas.demo', 'dept_head', $ict, $head, false, UserStatus::Active],
            ['STF-10004', 'Daniel Lee', 'staff@cas.demo', 'staff', $apd, $analyst, false, UserStatus::Active],
            ['STF-10005', 'Siti Aminah', 'siti@cas.demo', 'staff', $apd, $analyst, false, UserStatus::Locked],
        ];

        $transfer = app(TransferUser::class);
        $users = [];
        foreach ($accounts as [$staffId, $name, $email, $role, $unit, $position, $mfa, $status]) {
            $user = User::factory()->create([
                'staff_id' => $staffId,
                'name' => $name,
                'email' => $email,
                'status' => $status,
            ]);
            $user->forceFill(['mfa_enabled' => $mfa])->save();
            $user->assignRole($role);
            $transfer($user, $unit->id, $position?->id, now()->subMonths(6));
            $users[$email] = $user;
        }

        $hq->update(['head_user_id' => $users['superadmin@cas.demo']->id]);
        $ict->update(['head_user_id' => $users['dept.head@cas.demo']->id]);
        $apd->update(['head_user_id' => $users['dept.head@cas.demo']->id]);
    }
}
