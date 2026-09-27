<?php

namespace App\Http\Controllers\Access;

use App\Domain\Access\Actions\DeleteRole;
use App\Domain\Access\Actions\EnsureRoleManageable;
use App\Domain\Access\Actions\GrantablePermissions;
use App\Domain\Access\Actions\SyncRolePermissions;
use App\Http\Controllers\Controller;
use App\Http\Requests\Access\StoreRoleRequest;
use App\Http\Requests\Access\SyncPermissionsRequest;
use App\Http\Requests\Access\UpdateRoleRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function index(Request $request, GrantablePermissions $grantable, EnsureRoleManageable $manageable): Response
    {
        $this->authorize('viewAny', Role::class);

        $roles = Role::query()->withCount(['permissions', 'users'])->orderBy('name')->get();
        $selected = $roles->firstWhere('id', (int) $request->query('role')) ?? $roles->first();

        $detail = null;
        if ($selected) {
            try {
                $manageable($request->user(), $selected->loadMissing('permissions'));
                $reason = null;
            } catch (ValidationException $e) {
                $reason = $e->errors()['role'][0];
            }

            $detail = [
                'id' => $selected->id,
                'name' => $selected->name,
                'display_name' => $selected->getAttribute('display_name'),
                'description' => $selected->getAttribute('description'),
                'is_system' => (bool) $selected->getAttribute('is_system'),
                'users_count' => $selected->users_count,
                'permissions' => $selected->permissions->pluck('name')->sort()->values()->all(),
                'locked_reason' => $reason, // set when the actor may look but not change this role
            ];
        }

        return Inertia::render('Roles/Index', [
            'roles' => $roles->map(fn (Role $r) => [
                'id' => $r->id, 'name' => $r->name, 'display_name' => $r->getAttribute('display_name'), 'is_system' => (bool) $r->getAttribute('is_system'),
                'users_count' => $r->users_count, 'permissions_count' => $r->permissions_count,
            ])->all(),
            'selected' => $detail,
            'grid' => $this->grid(),
            'grantable' => $grantable($request->user())->all(),
            'can' => [
                'create' => $request->user()->can('create', Role::class),
                'update' => $request->user()->can('update', Role::class),
                'delete' => $request->user()->can('delete', Role::class),
            ],
        ]);
    }

    public function store(StoreRoleRequest $request): RedirectResponse
    {
        $role = Role::create($request->validated() + ['guard_name' => 'web', 'is_system' => false]);

        return redirect()->route('roles.index', ['role' => $role->id])->with('status', __('cas.roles.created'));
    }

    public function update(UpdateRoleRequest $request, Role $role, EnsureRoleManageable $manageable): RedirectResponse
    {
        $manageable($request->user(), $role->loadMissing('permissions'));
        $role->update($request->validated());

        return back()->with('status', __('cas.roles.updated'));
    }

    public function permissions(SyncPermissionsRequest $request, Role $role, SyncRolePermissions $sync): RedirectResponse
    {
        $sync($request->user(), $role->loadMissing('permissions'), $request->validated('permissions'));

        return back()->with('status', __('cas.roles.permissions_saved'));
    }

    public function destroy(Request $request, Role $role, DeleteRole $delete): RedirectResponse
    {
        $this->authorize('delete', Role::class);
        $delete($request->user(), $role->loadMissing('permissions'));

        return redirect()->route('roles.index')->with('status', __('cas.roles.deleted'));
    }

    /** @return list<array{module: string, actions: list<string>}> the permission grid: one row per module */
    private function grid(): array
    {
        // Insertion order (the seeder's module order) keeps the grid rows in the same order as the sidebar.
        return Permission::query()->orderBy('id')->get(['module', 'action'])
            ->groupBy('module')
            ->map(fn ($rows, $module) => ['module' => $module, 'actions' => $rows->pluck('action')->all()])
            ->values()->all();
    }
}
