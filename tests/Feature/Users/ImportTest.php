<?php

use App\Domain\Identity\Actions\Import\ImportUsers;
use App\Domain\Identity\Enums\ImportStatus;
use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Jobs\ProcessUserImport;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\UserImport;
use App\Domain\Identity\Notifications\InviteUser;
use App\Domain\Organization\Models\OrgUnit;
use App\Domain\Organization\Models\Position;
use Database\Seeders\AccessSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(AccessSeeder::class);
    Storage::fake('local');
});

function importer(string $role = 'hr_officer'): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

function csv_file(string $content, string $name = 'users.csv'): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, $content);
}

const HEADER = "staff_id,name,email,org_unit_code,position_title,role\n";

/** Uploads through the real endpoint. The test queue is synchronous, so the job has already run afterwards. */
function upload_csv($test, string $content): ?UserImport
{
    $test->post('/users/import', ['file' => csv_file($content)]);

    return UserImport::latest('id')->first();
}

it('offers a template and the upload page only to people who may create users', function () {
    $this->actingAs(importer())->get('/users/import/template')->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
    expect($this->get('/users/import/template')->streamedContent())->toStartWith('staff_id,name,email,org_unit_code,position_title,role');
    $this->get('/users/import')->assertOk()->assertInertia(fn ($p) => $p->component('Users/Import')->has('imports'));

    foreach (['dept_head', 'staff'] as $role) {
        $this->actingAs(importer($role))->get('/users/import')->assertForbidden();
        $this->post('/users/import', ['file' => csv_file(HEADER)])->assertForbidden();
        $this->get('/users/import/template')->assertForbidden();
    }
    expect(UserImport::count())->toBe(0);
});

it('validates the upload', function () {
    $this->actingAs(importer());

    $this->post('/users/import', [])->assertSessionHasErrors('file');
    $this->post('/users/import', ['file' => UploadedFile::fake()->image('users.png')])->assertSessionHasErrors('file');
    $this->post('/users/import', ['file' => UploadedFile::fake()->create('users.csv', config('cas.import.max_kb') + 1, 'text/csv')])->assertSessionHasErrors('file');
    expect(UserImport::count())->toBe(0);
});

it('queues the import and lets only one run per person at a time', function () {
    Queue::fake();
    $hr = importer();

    $this->actingAs($hr)->post('/users/import', ['file' => csv_file(HEADER)])->assertRedirect();
    $import = UserImport::firstOrFail();
    expect($import->status)->toBe(ImportStatus::Queued)->and($import->created_by)->toBe($hr->id);
    Queue::assertPushed(ProcessUserImport::class, fn ($job) => $job->importId === $import->id);
    Storage::disk('local')->assertExists($import->file_path);

    $this->post('/users/import', ['file' => csv_file(HEADER)])->assertSessionHasErrors('file');
    expect(UserImport::count())->toBe(1);

    $other = importer();
    $this->actingAs($other)->post('/users/import', ['file' => csv_file(HEADER)])->assertSessionHasNoErrors(); // someone else's import does not block
});

it('creates invited accounts with their unit, position and role', function () {
    Notification::fake();
    $unit = OrgUnit::factory()->create(['code' => 'ICT-APD']);
    $position = Position::factory()->create(['org_unit_id' => $unit->id, 'title' => 'Systems Analyst']);
    $hr = importer();

    $this->actingAs($hr);
    $import = upload_csv($this, HEADER
        ."stf-1,Ada Lovelace,ADA@X.TEST,ict-apd,systems analyst,staff\n"
        ."STF-2,\"Hopper, Grace\",grace@x.test,,,\n");

    expect($import->status)->toBe(ImportStatus::Completed)->and($import->total_rows)->toBe(2)->and($import->success_rows)->toBe(2)->and($import->failed_rows)->toBe(0);

    $ada = User::where('staff_id', 'STF-1')->firstOrFail();
    expect($ada->email)->toBe('ada@x.test')->and($ada->status)->toBe(UserStatus::Pending)->and($ada->password)->toBeNull()
        ->and($ada->created_by)->toBe($hr->id)->and($ada->org_unit_id)->toBe($unit->id)->and($ada->position_id)->toBe($position->id)
        ->and($ada->getRoleNames()->all())->toBe(['staff'])->and($ada->assignments()->whereNull('ended_at')->count())->toBe(1);
    expect(User::where('staff_id', 'STF-2')->value('name'))->toBe('Hopper, Grace');
    Notification::assertSentTo($ada, InviteUser::class);
    Notification::assertCount(2);
});

it('reads files with a BOM, Windows line endings, odd header spelling, extra columns and blank lines', function () {
    Notification::fake();
    $this->actingAs(importer());

    $import = upload_csv($this, "\xEF\xBB\xBFStaff ID, NAME ,E-mail,Phone\r\n\r\nSTF-3,Bob,bob@x.test,123\r\n,,,\r\n");

    expect($import->status)->toBe(ImportStatus::Failed)->and($import->errors[0]['message'])->toContain('email'); // "E-mail" is not "email"

    $import = upload_csv($this, "\xEF\xBB\xBFStaff ID, NAME ,Email,Phone\r\n\r\nSTF-3,Bob,bob@x.test,123\r\n,,,\r\n");
    expect($import->status)->toBe(ImportStatus::Completed)->and($import->total_rows)->toBe(1)->and(User::where('staff_id', 'STF-3')->exists())->toBeTrue();
});

it('keeps going after bad rows and reports each problem against its row number', function () {
    Notification::fake();
    User::factory()->create(['staff_id' => 'STF-TAKEN', 'email' => 'exists@x.test']);
    $unit = OrgUnit::factory()->create(['code' => 'U1']);
    Position::factory()->create(['org_unit_id' => $unit->id, 'title' => 'Clerk']);
    $this->actingAs(importer());

    $import = upload_csv($this, HEADER
        ."STF-10,Good One,good@x.test,,,\n"        // row 2 ok
        ."STF-10,Repeat Id,repeat@x.test,,,\n"      // row 3 staff_id repeated in the file
        ."STF-11,Repeat Mail,GOOD@x.test,,,\n"      // row 4 email repeated in the file
        ."STF-TAKEN,Taken,new@x.test,,,\n"          // row 5 staff id already exists
        ."STF-12,Taken Mail,exists@x.test,,,\n"     // row 6 email already exists
        ."STF-13,,x@x.test,,,\n"                    // row 7 name missing
        ."STF-14,Bad Mail,not-an-email,,,\n"        // row 8
        ."STF-15,Ghost Unit,g@x.test,NOPE,,\n"      // row 9 unknown unit
        ."STF-16,Loose Post,l@x.test,,Clerk,\n"     // row 10 position without unit
        ."STF-17,No Post,n@x.test,U1,Astronaut,\n"  // row 11 unknown position
        ."STF-18,Odd Role,o@x.test,,,super_admin\n" // row 12 hr cannot hand out super_admin
        ."STF-19,Also Good,fine@x.test,U1,clerk,staff\n"); // row 13 ok

    expect($import->status)->toBe(ImportStatus::Completed)->and($import->total_rows)->toBe(12)->and($import->success_rows)->toBe(2)->and($import->failed_rows)->toBe(10);
    $byRow = collect($import->errors)->groupBy('row')->map(fn ($g) => $g->pluck('field')->all());
    expect($byRow->all())->toBe([
        3 => ['staff_id'], 4 => ['email'], 5 => ['staff_id'], 6 => ['email'], 7 => ['name'], 8 => ['email'],
        9 => ['org_unit_code'], 10 => ['position_title'], 11 => ['position_title'], 12 => ['role'],
    ]);
    expect(User::whereIn('staff_id', ['STF-10', 'STF-19'])->count())->toBe(2)->and(User::where('staff_id', 'STF-18')->exists())->toBeFalse();
});

it('fails an unusable file without creating anyone', function (string $content, string $needle) {
    $this->actingAs(importer());
    $before = User::count();

    $import = upload_csv($this, $content);

    expect($import->status)->toBe(ImportStatus::Failed)->and($import->errors[0]['message'])->toContain($needle)->and(User::count())->toBe($before);
})->with([
    'empty file' => ['', 'empty'],
    'header only' => [HEADER, 'no rows'],
    'missing columns' => ["name,role\nA,staff\n", 'staff_id, email'],
]);

it('stops at the row limit and caps stored errors while still counting them', function () {
    Notification::fake();
    config(['cas.import.max_rows' => 3, 'cas.import.max_errors' => 2]);
    $this->actingAs(importer());

    $import = upload_csv($this, HEADER."A1,,a1@x.test,,,\nA2,,a2@x.test,,,\nA3,,a3@x.test,,,\nA4,Fourth,a4@x.test,,,\n");

    expect($import->total_rows)->toBe(3)->and($import->failed_rows)->toBe(3)
        ->and($import->errors)->toHaveCount(3) // 2 kept + the "more not shown" note
        ->and(collect($import->errors)->last()['message'])->toContain('2 more')
        ->and(User::where('staff_id', 'A4')->exists())->toBeFalse();
});

it('deletes the uploaded file and keeps personal data out of the error report', function () {
    Notification::fake();
    $this->actingAs(importer());

    $import = upload_csv($this, HEADER."STF-40,,secret.person@x.test,,,\n");

    Storage::disk('local')->assertMissing($import->file_path);
    expect(json_encode($import->errors))->not->toContain('secret.person');
});

it('refuses to run when the person lost the permission after uploading', function () {
    $hr = importer();
    Storage::disk('local')->put('imports/late.csv', HEADER."STF-50,Late,late@x.test,,,\n");
    $import = UserImport::create(['file_path' => 'imports/late.csv', 'status' => ImportStatus::Queued, 'created_by' => $hr->id]);
    $hr->removeRole('hr_officer');

    app(ImportUsers::class)($import);

    expect($import->refresh()->status)->toBe(ImportStatus::Failed)->and(User::where('staff_id', 'STF-50')->exists())->toBeFalse();
    Storage::disk('local')->assertMissing('imports/late.csv');
});

it('does not start a job twice or rerun one that already finished', function () {
    Notification::fake();
    $this->actingAs(importer());
    $import = upload_csv($this, HEADER."STF-60,Once,once@x.test,,,\n");

    (new ProcessUserImport($import->id))->handle(app(ImportUsers::class));

    expect(User::where('staff_id', 'STF-60')->count())->toBe(1)->and($import->refresh()->success_rows)->toBe(1);
});

it('shows results and the error report only to whoever started the import', function () {
    Notification::fake();
    $hr = importer();
    $this->actingAs($hr);
    $import = upload_csv($this, HEADER."STF-70,,x@x.test,,,\n");

    $this->get("/users/imports/$import->id")->assertOk()->assertInertia(fn ($p) => $p->component('Users/ImportShow')
        ->where('import.status', 'completed')->where('import.failed_rows', 1)->where('import.errors_total', 1)->has('import.errors', 1));
    expect($this->get("/users/imports/$import->id/errors.csv")->streamedContent())->toStartWith("row,field,message\n2,name,");

    $this->actingAs(importer())->get("/users/imports/$import->id")->assertForbidden();
    $this->get("/users/imports/$import->id/errors.csv")->assertForbidden();
    $this->actingAs(importer('super_admin'))->get("/users/imports/$import->id")->assertOk(); // Gate::before
});

it('neutralises spreadsheet formulas in the downloadable report', function () {
    $hr = importer();
    $import = UserImport::create([
        'file_path' => 'x', 'status' => ImportStatus::Completed, 'created_by' => $hr->id,
        'errors' => [['row' => 2, 'field' => '=HYPERLINK("http://evil")', 'message' => '@SUM(1+1)']],
    ]);

    $body = $this->actingAs($hr)->get("/users/imports/$import->id/errors.csv")->streamedContent();

    expect($body)->toContain("'=HYPERLINK")->toContain("'@SUM")->not->toContain(',=HYPERLINK');
});
