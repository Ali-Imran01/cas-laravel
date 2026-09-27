<?php

use App\Domain\Audit\Audit;
use App\Domain\Audit\Enums\AuthResult;
use App\Domain\Audit\Enums\LoginMethod;
use App\Domain\Audit\Models\AuditLog;
use App\Domain\Audit\Models\LoginAttempt;
use App\Domain\Identity\Models\User;
use App\Domain\Organization\Models\OrgUnit;
use Database\Seeders\AccessSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(AccessSeeder::class));

function auditor(string $role = 'super_admin'): User
{
    $user = User::factory()->create(['name' => 'Aisyah Rahman', 'staff_id' => 'STF-100']);
    $user->assignRole($role);

    return $user;
}

it('is closed to everyone without audit.view', function (string $role) {
    $this->actingAs(auditor($role))->get('/audit')->assertForbidden();
    $this->get('/audit?view=signins')->assertForbidden();
    $this->get('/audit/export')->assertForbidden();
    expect(AuditLog::where('action', 'EXPORT')->count())->toBe(0);
})->with(['hr_officer', 'dept_head', 'staff']);

it('sends guests to sign in', function () {
    $this->get('/audit')->assertRedirect(route('login'));
});

it('lists newest first with actor, subject, values and filter options', function () {
    $admin = auditor();
    $unit = OrgUnit::factory()->create();
    Audit::record('CREATE', 'First', $unit, [], ['code' => 'A'], actorId: $admin->id);
    Audit::record('UPDATE', 'Second', $unit, ['name' => 'x'], ['name' => 'y']);

    $this->actingAs($admin)->get('/audit')->assertInertia(fn (Assert $p) => $p
        ->component('Audit/Index')->where('view', 'log')
        ->has('entries.data', 2)
        ->where('entries.data.0.description', 'Second')->where('entries.data.0.actor', null)
        ->where('entries.data.0.subject', 'OrgUnit #'.$unit->id)->where('entries.data.0.new_values', ['name' => 'y'])
        ->where('entries.data.1.actor.staff_id', 'STF-100')
        ->where('actions', ['CREATE', 'UPDATE'])->where('subjects', ['OrgUnit'])->where('results', ['success', 'failed', 'blocked']));
});

it('filters by text, action, person, record type, result and date, and combines them', function () {
    $admin = auditor();
    $unit = OrgUnit::factory()->create();
    $user = User::factory()->create();
    Audit::record('CREATE', 'Created unit ICT-APD', $unit, actorId: $admin->id);
    Audit::record('LOCK', '100% lock_test', $user, result: AuthResult::Blocked);
    Audit::record('DELETE', 'Deleted user', $user, actorId: $user->id);
    DB::table('audit_logs')->insert(['action' => 'OLD', 'description' => 'Ancient', 'result' => 'success', 'created_at' => '2025-01-05 10:00:00']);
    $this->actingAs($admin);

    $count = fn (string $qs) => count($this->get("/audit?$qs")->viewData('page')['props']['entries']['data']);

    expect($count(''))->toBe(4)
        ->and($count('search='.urlencode('ICT-apd')))->toBe(1)
        ->and($count('search='.urlencode('100%')))->toBe(1)
        ->and($count('search='.urlencode('%')))->toBe(1) // a literal percent sign, not a wildcard
        ->and($count('search='.urlencode('lock_test')))->toBe(1)
        ->and($count('search='.urlencode('lock_x')))->toBe(0) // underscore is literal too
        ->and($count('action=LOCK'))->toBe(1)
        ->and($count('actor=aisyah'))->toBe(1)
        ->and($count('actor=STF-100'))->toBe(1)
        ->and($count('subject=OrgUnit'))->toBe(1)
        ->and($count('subject=User'))->toBe(2)
        ->and($count('result=blocked'))->toBe(1)
        ->and($count('from=2025-01-05&to=2025-01-05'))->toBe(1)
        ->and($count('to=2025-01-31'))->toBe(1)
        ->and($count('from=2025-01-06'))->toBe(3)
        ->and($count('subject=User&result=blocked'))->toBe(1);
});

it('rejects malformed filters', function (string $qs, string $field) {
    $this->actingAs(auditor())->get("/audit?$qs")->assertSessionHasErrors($field);
})->with([
    'bad result' => ['result=maybe', 'result'],
    'bad date' => ['from=yesterday', 'from'],
    'end before start' => ['from=2026-02-01&to=2026-01-01', 'to'],
    'too long' => ['search='.str_repeat('a', 101), 'search'],
]);

it('paginates 25 rows per page and keeps filters in the page links', function () {
    $admin = auditor();
    foreach (range(1, 30) as $i) {
        Audit::record('CREATE', "Row $i");
    }

    $this->actingAs($admin)->get('/audit?action=CREATE')->assertInertia(fn (Assert $p) => $p
        ->has('entries.data', 25)->where('entries.last_page', 2)
        ->where('entries.next_page_url', fn ($url) => str_contains($url, 'action=CREATE')));
});

it('shows sign-in attempts with their own filters', function () {
    $admin = auditor();
    $user = User::factory()->create();
    Audit::loginAttempt($user, 'STF-1', LoginMethod::Password, AuthResult::Success);
    Audit::loginAttempt(null, 'ghost@x.test', LoginMethod::Password, AuthResult::Failed, 'unknown_account');
    Audit::loginAttempt($user, 'STF-1', LoginMethod::Mfa, AuthResult::Blocked, 'throttled');
    $this->actingAs($admin);

    $count = fn (string $qs) => count($this->get("/audit?view=signins&$qs")->viewData('page')['props']['entries']['data']);

    expect($count(''))->toBe(3)
        ->and($count('search=GHOST'))->toBe(1)
        ->and($count('result=failed'))->toBe(1)
        ->and($count('method=mfa'))->toBe(1)
        ->and($count('method=mfa&result=success'))->toBe(0);
    $this->get('/audit?view=signins')->assertInertia(fn (Assert $p) => $p
        ->where('view', 'signins')->where('entries.data.0.reason', 'throttled')->where('methods', ['password', 'sso', 'mfa']));
    $this->get('/audit?view=signins&method=carrier-pigeon')->assertSessionHasErrors('method');
});

it('exports the filtered log as CSV and records the export', function () {
    $admin = auditor();
    Audit::record('CREATE', 'Kept', null, [], ['a' => 1], actorId: $admin->id);
    Audit::record('LOCK', 'Filtered out');
    $this->actingAs($admin);

    $response = $this->get('/audit/export?action=CREATE');
    $body = $response->streamedContent();

    $response->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
    $lines = array_filter(explode("\n", trim($body)));
    expect($lines)->toHaveCount(2)
        ->and($lines[0])->toBe('id,time,actor_staff_id,actor_name,action,subject,description,result,ip,old_values,new_values')
        ->and($lines[1])->toContain('STF-100')->toContain('Aisyah Rahman')->toContain('CREATE')->toContain('Kept')->not->toContain('Filtered out');

    $export = AuditLog::where('action', 'EXPORT')->latest('id')->firstOrFail();
    expect($export->actor_id)->toBe($admin->id)->and($export->new_values)->toBe(['filters' => ['action' => 'CREATE']]);
});

it('neutralises spreadsheet formulas in the export', function () {
    $admin = auditor();
    Audit::record('CREATE', '=HYPERLINK("http://evil")', null, [], [], actorId: $admin->id);

    $body = $this->actingAs($admin)->get('/audit/export?action=CREATE')->streamedContent();

    expect($body)->toContain("\"'=HYPERLINK")->not->toContain(',=HYPERLINK');
});

it('gives the dashboard real numbers and a 14-day sign-in series', function () {
    $admin = auditor();
    User::factory()->count(2)->create();
    LoginAttempt::create(['identifier' => 'a', 'method' => 'password', 'result' => 'success']);
    LoginAttempt::create(['identifier' => 'b', 'method' => 'password', 'result' => 'success']);
    LoginAttempt::create(['identifier' => 'c', 'method' => 'password', 'result' => 'failed']); // not counted
    DB::table('login_attempts')->insert(['identifier' => 'old', 'method' => 'password', 'result' => 'success', 'created_at' => now()->subDays(20)]); // outside the window

    $this->actingAs($admin)->get('/')->assertInertia(fn (Assert $p) => $p
        ->where('kpis.users', 3)->where('kpis.signInsToday', 2)
        ->has('signIns', 14)->where('signIns.13.count', 2)->where('signIns.13.date', today()->toDateString())->where('signIns.0.count', 0));
});

it('shows recent activity on the dashboard only to people who may read the audit log', function () {
    Audit::record('CREATE', 'Something');

    $this->actingAs(auditor())->get('/')->assertInertia(fn (Assert $p) => $p->has('recent', 1)->where('recent.0.description', 'Something'));
    $staff = User::factory()->create();
    $staff->assignRole('staff');
    $this->actingAs($staff)->get('/')->assertInertia(fn (Assert $p) => $p->where('recent', null));
});
