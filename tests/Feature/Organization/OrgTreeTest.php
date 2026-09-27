<?php

use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Models\User;
use App\Domain\Organization\Enums\OrgUnitType;
use App\Domain\Organization\Models\OrgUnit;
use App\Domain\Organization\Models\Position;
use Database\Seeders\AccessSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(AccessSeeder::class));

function org_actor(string $role = 'super_admin'): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

/** HQ > DIV > UNIT, plus a second branch HQ > OTHER. */
function sample_tree(): array
{
    $hq = OrgUnit::factory()->create(['type' => OrgUnitType::Headquarters, 'code' => 'HQ', 'name' => 'Headquarters']);
    $div = OrgUnit::factory()->create(['type' => OrgUnitType::Division, 'code' => 'DIV', 'name' => 'Division', 'parent_id' => $hq->id]);
    $unit = OrgUnit::factory()->create(['type' => OrgUnitType::Unit, 'code' => 'UNIT', 'name' => 'Unit', 'parent_id' => $div->id]);
    $other = OrgUnit::factory()->create(['type' => OrgUnitType::Division, 'code' => 'OTHER', 'name' => 'Other', 'parent_id' => $hq->id]);

    return [$hq, $div, $unit, $other];
}

it('is visible with organization.view but changeable only with the matching permission', function () {
    [, $div] = sample_tree();
    $hr = org_actor('hr_officer'); // organization.view only

    $this->actingAs($hr)->get('/organization')->assertOk()->assertInertia(fn (Assert $p) => $p->where('can.create', false)->where('can.update', false)->where('can.delete', false));
    $this->post('/organization/units', ['type' => 'unit', 'code' => 'X', 'name' => 'X', 'parent_id' => $div->id])->assertForbidden();
    $this->put("/organization/units/$div->id", ['type' => 'division', 'code' => 'DIV', 'name' => 'Renamed', 'is_active' => true])->assertForbidden();
    $this->post("/organization/units/$div->id/move", ['parent_id' => null])->assertForbidden();
    $this->post("/organization/units/$div->id/positions", ['title' => 'T', 'headcount' => 1])->assertForbidden();
    $this->delete("/organization/units/$div->id")->assertForbidden();

    $this->actingAs(org_actor('staff'))->get('/organization')->assertForbidden();
    auth()->logout();
    $this->get('/organization')->assertRedirect(route('login'));
});

it('shows the tree in order with depth, headcount of people and the selected unit', function () {
    [$hq, $div, $unit] = sample_tree();
    $head = User::factory()->create(['org_unit_id' => $unit->id]);
    $unit->update(['head_user_id' => $head->id]);
    Position::factory()->create(['org_unit_id' => $unit->id, 'title' => 'Analyst', 'headcount' => 3]);

    $response = $this->actingAs(org_actor())->get("/organization?unit=$unit->id");
    $units = $response->viewData('page')['props']['units'];
    expect(array_column($units, 'code'))->toBe(['HQ', 'DIV', 'UNIT', 'OTHER'])
        ->and(array_column($units, 'depth'))->toBe([0, 1, 2, 1]);

    $response->assertInertia(fn (Assert $p) => $p
        ->component('Organization/Index')
        ->where('units.2.users_count', 1)
        ->where('units.2.head', $head->name)
        ->where('selected.id', $unit->id)
        ->where('selected.path', ['Headquarters', 'Division'])
        ->where('selected.positions.0.title', 'Analyst')
        ->where('selected.members.0.id', $head->id));

    // Without ?unit the first unit is shown.
    $this->get('/organization')->assertInertia(fn (Assert $p) => $p->where('selected.id', $hq->id));
    $this->get('/organization?unit=999999')->assertInertia(fn (Assert $p) => $p->where('selected', null));
});

it('creates units and keeps the nested set intact', function () {
    $this->actingAs(org_actor());

    $this->post('/organization/units', ['type' => 'headquarters', 'code' => 'hq', 'name' => 'HQ'])->assertSessionHasNoErrors();
    $hq = OrgUnit::firstWhere('code', 'HQ');
    $this->post('/organization/units', ['type' => 'division', 'code' => 'ict', 'name' => 'ICT', 'parent_id' => $hq->id, 'cost_centre' => 'CC1'])->assertSessionHasNoErrors();

    $ict = OrgUnit::firstWhere('code', 'ICT');
    expect($ict->parent_id)->toBe($hq->id)->and($ict->cost_centre)->toBe('CC1')->and(OrgUnit::isBroken())->toBeFalse();
});

it('only lets headquarters be a root and keeps everything else under a parent', function () {
    [$hq, $div] = sample_tree();
    $this->actingAs(org_actor());

    $this->post('/organization/units', ['type' => 'headquarters', 'code' => 'HQ2', 'name' => 'H', 'parent_id' => $hq->id])->assertSessionHasErrors('parent_id');
    $this->post('/organization/units', ['type' => 'unit', 'code' => 'LOOSE', 'name' => 'L'])->assertSessionHasErrors('parent_id');
    $this->put("/organization/units/$div->id", ['type' => 'headquarters', 'code' => 'DIV', 'name' => 'Division', 'is_active' => true])->assertSessionHasErrors('type');
    expect($div->refresh()->type)->toBe(OrgUnitType::Division);
});

it('validates codes, including ones held by deleted units', function () {
    [$hq, $div, $unit] = sample_tree();
    $unit->delete();
    $this->actingAs(org_actor());

    $this->post('/organization/units', ['type' => 'unit', 'code' => 'UNIT', 'name' => 'x', 'parent_id' => $div->id])->assertSessionHasErrors('code');
    $this->post('/organization/units', ['type' => 'unit', 'code' => 'bad code', 'name' => 'x', 'parent_id' => $div->id])->assertSessionHasErrors('code');
    $this->post('/organization/units', ['type' => 'nope', 'code' => 'OK', 'name' => '', 'parent_id' => 999999])->assertSessionHasErrors(['type', 'name', 'parent_id']);
    $this->put("/organization/units/$div->id", ['type' => 'division', 'code' => 'DIV', 'name' => 'Same code is fine', 'is_active' => true])->assertSessionHasNoErrors();
    $this->put("/organization/units/$div->id", ['type' => 'division', 'code' => 'HQ', 'name' => 'x', 'is_active' => true])->assertSessionHasErrors('code');
});

it('sets a unit head only from active people in that unit or below it', function () {
    [$hq, $div, $unit, $other] = sample_tree();
    $below = User::factory()->create(['org_unit_id' => $unit->id]);
    $elsewhere = User::factory()->create(['org_unit_id' => $other->id]);
    $inactive = User::factory()->create(['org_unit_id' => $div->id, 'status' => UserStatus::Inactive]);
    $this->actingAs(org_actor());
    $payload = fn ($head) => ['type' => 'division', 'code' => 'DIV', 'name' => 'Division', 'is_active' => true, 'head_user_id' => $head];

    $this->put("/organization/units/$div->id", $payload($elsewhere->id))->assertSessionHasErrors('head_user_id');
    $this->put("/organization/units/$div->id", $payload($inactive->id))->assertSessionHasErrors('head_user_id');
    $this->put("/organization/units/$div->id", $payload(999999))->assertSessionHasErrors('head_user_id');
    $this->put("/organization/units/$div->id", $payload($below->id))->assertSessionHasNoErrors();
    expect($div->refresh()->head_user_id)->toBe($below->id);

    $this->put("/organization/units/$div->id", $payload(null))->assertSessionHasNoErrors();
    expect($div->refresh()->head_user_id)->toBeNull();
});

it('deactivates a unit without deleting it', function () {
    [, $div] = sample_tree();

    $this->actingAs(org_actor())->put("/organization/units/$div->id", ['type' => 'division', 'code' => 'DIV', 'name' => 'Division', 'is_active' => false]);

    expect($div->refresh()->is_active)->toBeFalse();
});

it('moves a unit with everything below it', function () {
    [$hq, $div, $unit, $other] = sample_tree();

    $this->actingAs(org_actor())->post("/organization/units/$div->id/move", ['parent_id' => $other->id])->assertSessionHasNoErrors();

    expect($div->refresh()->parent_id)->toBe($other->id)
        ->and($unit->refresh()->ancestors()->pluck('code')->all())->toBe(['HQ', 'OTHER', 'DIV'])
        ->and(OrgUnit::isBroken())->toBeFalse();
});

it('refuses moves that would break the tree', function () {
    [$hq, $div, $unit] = sample_tree();
    $this->actingAs(org_actor());

    $this->post("/organization/units/$div->id/move", ['parent_id' => $unit->id])->assertSessionHasErrors('parent_id'); // into own sub-unit
    $this->post("/organization/units/$div->id/move", ['parent_id' => $div->id])->assertSessionHasErrors('parent_id'); // into itself
    $this->post("/organization/units/$hq->id/move", ['parent_id' => $div->id])->assertSessionHasErrors('parent_id'); // HQ under its own child
    $this->post("/organization/units/$div->id/move", ['parent_id' => null])->assertSessionHasErrors('parent_id'); // a division cannot be a root
    $this->post("/organization/units/$div->id/move", ['parent_id' => 999999])->assertSessionHasErrors('parent_id');

    expect($div->refresh()->parent_id)->toBe($hq->id)->and(OrgUnit::isBroken())->toBeFalse();
});

it('deletes only empty leaf units and takes their positions with them', function () {
    [$hq, $div, $unit] = sample_tree();
    $position = Position::factory()->create(['org_unit_id' => $unit->id]);
    $person = User::factory()->create(['org_unit_id' => $unit->id]);
    $this->actingAs(org_actor());

    $this->delete("/organization/units/$div->id")->assertSessionHasErrors('unit'); // has a sub-unit
    $this->delete("/organization/units/$unit->id")->assertSessionHasErrors('unit'); // has a person
    expect(OrgUnit::find($unit->id))->not->toBeNull();

    $person->update(['org_unit_id' => null]);
    $this->delete("/organization/units/$unit->id")->assertRedirect(route('organization.index', ['unit' => $div->id]));

    expect(OrgUnit::find($unit->id))->toBeNull()->and(OrgUnit::withTrashed()->find($unit->id))->not->toBeNull()
        ->and(Position::find($position->id))->toBeNull()->and(OrgUnit::isBroken())->toBeFalse();
});

it('manages positions and protects the ones people hold', function () {
    [, , $unit] = sample_tree();
    $this->actingAs(org_actor());

    $this->post("/organization/units/$unit->id/positions", ['title' => 'Analyst', 'grade' => 'F41', 'headcount' => 3])->assertSessionHasNoErrors();
    $position = $unit->positions()->firstOrFail();
    User::factory()->count(2)->create(['org_unit_id' => $unit->id, 'position_id' => $position->id]);

    $this->put("/organization/positions/$position->id", ['title' => 'Analyst', 'headcount' => 1])->assertSessionHasErrors('headcount'); // 2 people hold it
    $this->put("/organization/positions/$position->id", ['title' => 'Senior Analyst', 'grade' => 'F44', 'headcount' => 2])->assertSessionHasNoErrors();
    expect($position->refresh()->title)->toBe('Senior Analyst')->and($position->headcount)->toBe(2);

    $this->delete("/organization/positions/$position->id")->assertSessionHasErrors('position');
    $position->users()->update(['position_id' => null]);
    $this->delete("/organization/positions/$position->id")->assertSessionHasNoErrors();
    expect(Position::find($position->id))->toBeNull();
});

it('validates positions', function () {
    [, , $unit] = sample_tree();
    $this->actingAs(org_actor());

    $this->post("/organization/units/$unit->id/positions", ['title' => '', 'headcount' => 0])->assertSessionHasErrors(['title', 'headcount']);
    $this->post("/organization/units/$unit->id/positions", ['title' => 'x', 'headcount' => 10000])->assertSessionHasErrors('headcount');
});
