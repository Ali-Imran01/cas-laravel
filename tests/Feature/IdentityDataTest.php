<?php

use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Models\User;
use App\Domain\Organization\Models\OrgUnit;
use App\Domain\Organization\Models\Position;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('creates every phase 1 table', function () {
    foreach (['org_units', 'positions', 'users', 'user_assignments', 'password_histories', 'user_imports'] as $table) {
        expect(Schema::hasTable($table))->toBeTrue($table);
    }
});

it('builds the org tree with ancestors and descendants', function () {
    $hq = OrgUnit::factory()->create(['code' => 'HQ']);
    $div = OrgUnit::factory()->create(['code' => 'DIV', 'parent_id' => $hq->id]);
    $unit = OrgUnit::factory()->create(['code' => 'UNIT', 'parent_id' => $div->id]);

    $hq->refresh(); // nested-set bounds (_lft/_rgt) change in the DB as children are inserted

    expect($unit->ancestors()->pluck('code')->all())->toBe(['HQ', 'DIV'])
        ->and($hq->descendants()->pluck('code')->all())->toBe(['DIV', 'UNIT']);
});

it('defaults a bare user to pending and hides security fields', function () {
    $user = User::create(['staff_id' => 'STF-1', 'name' => 'A', 'email' => 'a@x.test'])->refresh();

    expect($user->status)->toBe(UserStatus::Pending)
        ->and($user->password)->toBeNull()
        ->and($user->toArray())->not->toHaveKeys(['password', 'mfa_secret', 'mfa_recovery_codes']);
});

it('encrypts the mfa secret at rest', function () {
    $user = User::factory()->create();
    $user->forceFill(['mfa_secret' => 'JBSWY3DPEHPK3PXP'])->save();

    expect(DB::table('users')->where('id', $user->id)->value('mfa_secret'))->not->toBe('JBSWY3DPEHPK3PXP')
        ->and($user->refresh()->mfa_secret)->toBe('JBSWY3DPEHPK3PXP');
});

it('does not allow mass-assigning lockout or mfa state', function () {
    $user = User::factory()->create();
    $user->fill(['failed_login_count' => 9, 'mfa_enabled' => true, 'locked_until' => now()->addDay()])->save();

    expect($user->refresh()->failed_login_count)->toBe(0)->and($user->mfa_enabled)->toBeFalse();
});

it('keeps the org_units <-> users cycle consistent and nulls the head when the user is deleted for good', function () {
    $unit = OrgUnit::factory()->create();
    $head = User::factory()->create(['org_unit_id' => $unit->id]);
    $unit->update(['head_user_id' => $head->id]);

    expect($unit->head->is($head))->toBeTrue();

    $head->forceDelete();

    expect($unit->refresh()->head_user_id)->toBeNull();
});

it('links positions to units and users', function () {
    $position = Position::factory()->create();
    $user = User::factory()->create(['org_unit_id' => $position->org_unit_id, 'position_id' => $position->id]);

    expect($position->users->pluck('id')->all())->toBe([$user->id])
        ->and($user->orgUnit->is($position->orgUnit))->toBeTrue();
});
