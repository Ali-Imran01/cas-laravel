<?php

use App\Domain\Audit\Audit;
use App\Domain\Audit\Enums\AuthResult;
use App\Domain\Audit\Models\AuditLog;
use App\Domain\Audit\Models\LoginAttempt;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Notifications\InviteUser;
use App\Domain\Organization\Models\OrgUnit;
use App\Domain\Organization\Models\Position;
use Database\Seeders\AccessSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use PragmaRX\Google2FA\Google2FA;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(AccessSeeder::class));

function audited_admin(): User
{
    $user = User::factory()->create();
    $user->assignRole('super_admin');

    return $user;
}

function last_audit(string $action): AuditLog
{
    return AuditLog::where('action', $action)->latest('id')->firstOrFail();
}

it('refuses to change or delete history, in the model and in the database', function () {
    $log = Audit::record('CREATE', 'Something happened');

    expect(fn () => $log->update(['description' => 'edited']))->toThrow(LogicException::class);
    expect(fn () => $log->delete())->toThrow(LogicException::class);

    // Bypassing the model still fails: the database trigger rejects it. Each attempt runs in its own savepoint.
    expect(fn () => DB::transaction(fn () => DB::table('audit_logs')->update(['description' => 'edited'])))->toThrow(Exception::class, 'append-only');
    expect(fn () => DB::transaction(fn () => DB::table('audit_logs')->delete()))->toThrow(Exception::class, 'append-only');
    expect($log->refresh()->description)->toBe('Something happened');
});

it('keeps history when the people it mentions are deleted for good', function () {
    $user = User::factory()->create();
    Audit::record('UPDATE', 'Touched', $user, actorId: $user->id);

    $user->forceDelete();

    expect(AuditLog::count())->toBe(1)->and(AuditLog::first()->actor_id)->toBe($user->id);
});

it('records who, what, from where, and redacts secrets everywhere', function () {
    $actor = User::factory()->create();
    $this->actingAs($actor);

    $log = Audit::record('UPDATE', str_repeat('x', 400), $actor, ['password' => 'hunter2', 'name' => 'Old'], [
        'mfa_secret' => 'JBSWY3DPEHPK3PXP', 'name' => 'New', 'nested' => ['api_token' => 'abc', 'recovery_codes' => ['a'], 'keep' => 1],
    ]);

    expect($log->actor_id)->toBe($actor->id)
        ->and($log->auditable_type)->toBe(User::class)->and($log->auditable_id)->toBe($actor->id)
        ->and(strlen($log->description))->toBe(255)
        ->and($log->old_values)->toBe(['password' => '[redacted]', 'name' => 'Old'])
        ->and($log->new_values)->toBe(['mfa_secret' => '[redacted]', 'name' => 'New', 'nested' => ['api_token' => '[redacted]', 'recovery_codes' => '[redacted]', 'keep' => 1]])
        ->and($log->ip_address)->toBe('127.0.0.1')
        ->and(json_encode($log->getAttributes()))->not->toContain('hunter2')->not->toContain('JBSWY3DPEHPK3PXP');
});

it('attributes work outside a web session to the right actor, and falls back to System', function () {
    $someone = User::factory()->create();

    expect(Audit::record('CREATE', 'a')->actor_id)->toBeNull();
    expect(Audit::asActor($someone->id, fn () => Audit::record('CREATE', 'b')->actor_id))->toBe($someone->id);
    expect(Audit::record('CREATE', 'c')->actor_id)->toBeNull(); // override does not leak
    expect(Audit::record('CREATE', 'd', actorId: $someone->id)->actor_id)->toBe($someone->id);
});

it('reports only what changed', function () {
    [$old, $new] = Audit::diff(['a' => 1, 'b' => 'x', 'c' => null], ['a' => 1, 'b' => 'y', 'c' => 3]);

    expect($old)->toBe(['b' => 'x', 'c' => null])->and($new)->toBe(['b' => 'y', 'c' => 3]);
});

it('records every kind of sign-in outcome', function () {
    $user = User::factory()->create(['staff_id' => 'STF-1', 'email' => 'a@x.test', 'password' => 'Old-Passw0rd!x1']);
    $inactive = User::factory()->create(['staff_id' => 'STF-2', 'password' => 'Old-Passw0rd!x1', 'status' => 'inactive']);

    $this->post('/login', ['identifier' => 'ghost', 'password' => 'nope-nope-nope']);
    $this->post('/login', ['identifier' => 'STF-1', 'password' => 'nope-nope-nope']);
    $this->post('/login', ['identifier' => 'STF-2', 'password' => 'Old-Passw0rd!x1']);
    $this->post('/login', ['identifier' => 'STF-1', 'password' => 'Old-Passw0rd!x1']);

    $rows = LoginAttempt::orderBy('id')->get(['identifier', 'user_id', 'method', 'result', 'failure_reason'])
        ->map(fn ($a) => [$a->identifier, $a->user_id, $a->method->value, $a->result->value, $a->failure_reason])->all();

    expect($rows)->toBe([
        ['ghost', null, 'password', 'failed', 'unknown_account'],
        ['STF-1', $user->id, 'password', 'failed', 'bad_password'],
        ['STF-2', $inactive->id, 'password', 'blocked', 'inactive'],
        ['STF-1', $user->id, 'password', 'success', null],
    ]);
    expect(LoginAttempt::first()->ip_address)->toBe('127.0.0.1');
});

it('logs the lockout and the blocked attempt that follows', function () {
    $user = User::factory()->create(['staff_id' => 'STF-1', 'password' => 'Old-Passw0rd!x1']);

    foreach (range(1, 5) as $_) {
        $this->post('/login', ['identifier' => 'STF-1', 'password' => 'nope-nope-nope']);
    }
    $this->post('/login', ['identifier' => 'STF-1', 'password' => 'Old-Passw0rd!x1']);

    $lockout = last_audit('LOCKOUT');
    expect($lockout->auditable_id)->toBe($user->id)->and($lockout->result)->toBe(AuthResult::Blocked)
        ->and(LoginAttempt::where('failure_reason', 'locked')->count())->toBe(1);
});

it('logs throttling and sign-outs', function () {
    foreach (range(1, 11) as $_) {
        $this->post('/login', ['identifier' => 'ghost', 'password' => 'nope-nope-nope']);
    }
    expect(LoginAttempt::where('failure_reason', 'throttled')->where('result', 'blocked')->count())->toBe(1);

    $user = User::factory()->create();
    $this->actingAs($user)->post('/logout');
    expect(last_audit('LOGOUT')->actor_id)->toBe($user->id);
});

it('records the second factor as its own kind of attempt', function () {
    $user = User::factory()->create(['staff_id' => 'STF-9', 'password' => 'Old-Passw0rd!x1']);
    $user->forceFill(['mfa_enabled' => true, 'mfa_secret' => 'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP', 'mfa_recovery_codes' => ['aaaaa-11111']])->save();

    $this->post('/login', ['identifier' => 'STF-9', 'password' => 'Old-Passw0rd!x1']);
    $this->post('/login/mfa', ['method' => 'totp', 'code' => '000000']);
    $this->post('/login/mfa', ['method' => 'recovery', 'code' => 'aaaaa-11111']);

    $mfa = LoginAttempt::where('method', 'mfa')->orderBy('id')->get(['result', 'failure_reason'])->map(fn ($a) => [$a->result->value, $a->failure_reason])->all();
    expect($mfa)->toBe([['failed', 'bad_totp_code'], ['success', null]]);
    expect(LoginAttempt::where('method', 'password')->count())->toBe(0); // the password step alone is not a completed sign-in
});

it('audits the user lifecycle without leaking secrets', function () {
    Notification::fake();
    $admin = audited_admin();
    $this->actingAs($admin);

    $this->post('/users', ['staff_id' => 'stf-50', 'name' => 'New', 'email' => 'new@x.test', 'role' => 'staff'])->assertSessionHasNoErrors();
    $user = User::where('staff_id', 'STF-50')->firstOrFail();
    $create = last_audit('CREATE');
    expect($create->actor_id)->toBe($admin->id)->and($create->auditable_id)->toBe($user->id)->and($create->new_values['role'])->toBe('staff');
    expect(AuditLog::where('action', 'INVITE')->count())->toBe(1)->and(AuditLog::where('action', 'TRANSFER')->count())->toBe(0);

    $this->put("/users/$user->id", ['staff_id' => 'STF-50', 'name' => 'Renamed', 'email' => 'new@x.test', 'role' => 'staff']);
    $update = last_audit('UPDATE');
    expect($update->old_values)->toBe(['name' => 'New'])->and($update->new_values)->toBe(['name' => 'Renamed']);

    $this->put("/users/$user->id", ['staff_id' => 'STF-50', 'name' => 'Renamed', 'email' => 'new@x.test', 'role' => 'staff']);
    expect(AuditLog::where('action', 'UPDATE')->count())->toBe(1); // no change, no entry

    $user->forceFill(['status' => 'active', 'password' => 'Old-Passw0rd!x1'])->save();
    foreach (['lock' => 'LOCK', 'unlock' => 'UNLOCK', 'deactivate' => 'DEACTIVATE', 'reactivate' => 'REACTIVATE'] as $route => $action) {
        $this->post("/users/$user->id/$route")->assertSessionHasNoErrors();
        expect(last_audit($action)->auditable_id)->toBe($user->id);
    }
    expect(last_audit('LOCK')->old_values)->toBe(['status' => 'active'])->and(last_audit('LOCK')->new_values)->toBe(['status' => 'locked']);

    $this->delete("/users/$user->id");
    expect(last_audit('DELETE')->old_values['staff_id'])->toBe('STF-50');
});

it('audits transfers, but not the first placement of a new account', function () {
    $admin = audited_admin();
    $a = OrgUnit::factory()->create();
    $b = OrgUnit::factory()->create();
    $user = User::factory()->create(['org_unit_id' => $a->id]);

    $this->actingAs($admin)->post("/users/$user->id/transfer", ['org_unit_id' => $b->id, 'started_at' => '2026-01-01']);

    $log = last_audit('TRANSFER');
    expect($log->old_values['org_unit_id'])->toBe($a->id)->and($log->new_values)->toEqual(['org_unit_id' => $b->id, 'position_id' => null, 'effective' => '2026-01-01']);
});

it('audits password, invitation and MFA events as the person themselves, with no password in the entry', function () {
    Notification::fake();
    $user = User::factory()->create(['password' => 'Old-Passw0rd!x1']);

    $this->actingAs($user)->put('/password/change', ['current_password' => 'Old-Passw0rd!x1', 'password' => 'New-Passw0rd!x2', 'password_confirmation' => 'New-Passw0rd!x2']);
    $log = last_audit('PASSWORD_CHANGE');
    expect($log->actor_id)->toBe($user->id)->and($log->new_values)->toBe(['via' => 'change'])
        ->and(json_encode($log->getAttributes()))->not->toContain('New-Passw0rd');

    $this->get('/mfa');
    $this->post('/mfa', ['code' => '000000']); // wrong code: nothing to audit
    expect(AuditLog::where('action', 'MFA_ENABLE')->count())->toBe(0);
    $secret = session('mfa_setup_secret');
    $this->post('/mfa', ['code' => (new Google2FA)->getCurrentOtp($secret)]);
    expect(last_audit('MFA_ENABLE')->actor_id)->toBe($user->id);
    $this->delete('/mfa', ['current_password' => 'New-Passw0rd!x2']);
    expect(last_audit('MFA_DISABLE')->old_values)->toBe(['mfa_enabled' => true]);

    $pending = User::factory()->pending()->create(['email' => 'inv@x.test']);
    $admin = audited_admin();
    $this->actingAs($admin)->post("/users/$pending->id/invite");
    $token = '';
    Notification::assertSentTo($pending, InviteUser::class, function ($n) use (&$token) {
        $token = $n->token;

        return true;
    });
    auth()->logout();
    $this->post('/accept-invite', ['token' => $token, 'email' => 'inv@x.test', 'password' => 'Fresh-Passw0rd!x9', 'password_confirmation' => 'Fresh-Passw0rd!x9']);

    expect(last_audit('ACTIVATE')->actor_id)->toBe($pending->id)
        ->and(AuditLog::where('action', 'PASSWORD_CHANGE')->latest('id')->first()->new_values)->toBe(['via' => 'invite']);
});

it('audits organization and role changes', function () {
    $this->actingAs(audited_admin());

    $this->post('/organization/units', ['type' => 'headquarters', 'code' => 'HQ', 'name' => 'HQ']);
    $hq = OrgUnit::firstWhere('code', 'HQ');
    $this->post('/organization/units', ['type' => 'division', 'code' => 'DIV', 'name' => 'Div', 'parent_id' => $hq->id]);
    $div = OrgUnit::firstWhere('code', 'DIV');
    $this->put("/organization/units/$div->id", ['type' => 'division', 'code' => 'DIV', 'name' => 'Division', 'is_active' => true]);
    expect(last_audit('UPDATE')->old_values)->toBe(['name' => 'Div']);

    $this->post("/organization/units/$div->id/positions", ['title' => 'Analyst', 'headcount' => 2]);
    $position = $div->positions()->first();
    $this->put("/organization/positions/$position->id", ['title' => 'Analyst', 'headcount' => 3]);
    $this->delete("/organization/positions/$position->id");
    expect(AuditLog::where('auditable_type', Position::class)->orderBy('id')->pluck('action')->all())->toBe(['CREATE', 'UPDATE', 'DELETE']);

    $other = OrgUnit::factory()->create(['type' => 'division', 'parent_id' => $hq->id]);
    $this->post("/organization/units/$div->id/move", ['parent_id' => $other->id]);
    expect(last_audit('MOVE')->new_values)->toBe(['parent_id' => $other->id]);
    $this->delete("/organization/units/$div->id");
    expect(last_audit('DELETE')->old_values['code'])->toBe('DIV');

    $this->post('/roles', ['name' => 'desk', 'display_name' => 'Desk']);
    $role = Role::findByName('desk');
    $this->put("/roles/$role->id", ['display_name' => 'Service desk']);
    $this->put("/roles/$role->id/permissions", ['permissions' => ['users.view', 'audit.view']]);
    $perm = last_audit('PERMISSIONS');
    expect($perm->old_values)->toBe(['permissions' => []])->and($perm->new_values)->toBe(['permissions' => ['audit.view', 'users.view']]);
    $this->delete("/roles/$role->id");
    expect(AuditLog::where('auditable_type', Role::class)->orderBy('id')->pluck('action')->all())->toBe(['CREATE', 'UPDATE', 'PERMISSIONS', 'DELETE']);
});

it('attributes an import, and every account it creates, to the person who started it', function () {
    Notification::fake();
    Storage::fake('local');
    $hr = User::factory()->create();
    $hr->assignRole('hr_officer');

    $this->actingAs($hr)->post('/users/import', ['file' => UploadedFile::fake()->createWithContent('u.csv', "staff_id,name,email\nSTF-70,One,one@x.test\nSTF-71,Two,two@x.test\n")]);

    expect(AuditLog::where('action', 'IMPORT_START')->value('actor_id'))->toBe($hr->id)
        ->and(AuditLog::where('action', 'CREATE')->pluck('actor_id')->unique()->all())->toBe([$hr->id])
        ->and(AuditLog::where('action', 'CREATE')->count())->toBe(2)
        ->and(last_audit('IMPORT_DONE')->new_values)->toEqual(['rows' => 2, 'created' => 2, 'failed' => 0])
        ->and(last_audit('IMPORT_DONE')->actor_id)->toBe($hr->id);
});
