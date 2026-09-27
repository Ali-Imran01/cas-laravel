<?php

namespace App\Http\Controllers\Organization;

use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Models\User;
use App\Domain\Organization\Actions\CreateOrgUnit;
use App\Domain\Organization\Actions\DeleteOrgUnit;
use App\Domain\Organization\Actions\MoveOrgUnit;
use App\Domain\Organization\Actions\UpdateOrgUnit;
use App\Domain\Organization\Enums\OrgUnitType;
use App\Domain\Organization\Models\OrgUnit;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organization\MoveOrgUnitRequest;
use App\Http\Requests\Organization\StoreOrgUnitRequest;
use App\Http\Requests\Organization\UpdateOrgUnitRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OrgUnitController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', OrgUnit::class);

        $meta = OrgUnit::query()->withCount('users')->with('head:id,name')->get()->keyBy('id');
        $units = array_map(fn (array $row) => $row + [
            'parent_id' => $meta[$row['id']]->parent_id,
            'type' => $meta[$row['id']]->type->value,
            'code' => $meta[$row['id']]->code,
            'is_active' => $meta[$row['id']]->is_active,
            'users_count' => $meta[$row['id']]->users_count,
            'head' => $meta[$row['id']]->head?->name,
        ], OrgUnit::outline());

        $selectedId = (int) $request->query('unit', $units[0]['id'] ?? 0);
        $selected = $meta->get($selectedId);

        return Inertia::render('Organization/Index', [
            'units' => $units,
            'selected' => $selected ? $this->detail($selected) : null,
            'types' => array_column(OrgUnitType::cases(), 'value'),
            'can' => [
                'create' => $request->user()->can('create', OrgUnit::class),
                'update' => $request->user()->can('update', OrgUnit::class),
                'delete' => $request->user()->can('delete', OrgUnit::class),
            ],
        ]);
    }

    public function store(StoreOrgUnitRequest $request, CreateOrgUnit $create): RedirectResponse
    {
        $unit = $create($request->validated());

        return redirect()->route('organization.index', ['unit' => $unit->id])->with('status', __('cas.org.created'));
    }

    public function update(UpdateOrgUnitRequest $request, OrgUnit $unit, UpdateOrgUnit $update): RedirectResponse
    {
        $update($unit, $request->validated());

        return back()->with('status', __('cas.org.updated'));
    }

    public function move(MoveOrgUnitRequest $request, OrgUnit $unit, MoveOrgUnit $move): RedirectResponse
    {
        $move($unit, $request->validated('parent_id'));

        return back()->with('status', __('cas.org.moved'));
    }

    public function destroy(OrgUnit $unit, DeleteOrgUnit $delete): RedirectResponse
    {
        $this->authorize('delete', OrgUnit::class);
        $parentId = $unit->parent_id;
        $delete($unit);

        return redirect()->route('organization.index', array_filter(['unit' => $parentId]))->with('status', __('cas.org.deleted'));
    }

    /** @return array<string, mixed> */
    private function detail(OrgUnit $unit): array
    {
        $filled = $unit->positions()->withCount('users')->orderBy('title')->get();

        return [
            'id' => $unit->id,
            'parent_id' => $unit->parent_id,
            'type' => $unit->type->value,
            'code' => $unit->code,
            'name' => $unit->name,
            'cost_centre' => $unit->cost_centre,
            'is_active' => $unit->is_active,
            'head_user_id' => $unit->head_user_id,
            'path' => OrgUnit::query()->where('_lft', '<', $unit->_lft)->where('_rgt', '>', $unit->_rgt)->orderBy('_lft')->pluck('name')->all(),
            'children_count' => $unit->children()->count(),
            'users_count' => $unit->users()->count(),
            'positions' => $filled->map(fn ($p) => [
                'id' => $p->id, 'title' => $p->title, 'grade' => $p->grade, 'headcount' => $p->headcount, 'filled' => $p->users_count,
            ])->all(),
            // Candidates for the unit head: active people in this unit or below it.
            'members' => User::query()->where('status', UserStatus::Active->value)->inUnitTree($unit->id)->orderBy('name')->get(['id', 'name', 'staff_id'])->all(),
        ];
    }
}
