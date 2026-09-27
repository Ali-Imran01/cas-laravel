<?php

namespace App\Http\Controllers\Users;

use App\Domain\Access\Actions\AssignableRoles;
use App\Domain\Identity\Actions\CreateUser;
use App\Domain\Identity\Actions\DeleteUser;
use App\Domain\Identity\Actions\UpdateUser;
use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Support\UserPayload;
use App\Domain\Organization\Models\OrgUnit;
use App\Domain\Organization\Models\Position;
use App\Http\Controllers\Controller;
use App\Http\Requests\Users\StoreUserRequest;
use App\Http\Requests\Users\UpdateUserRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    private const SORTS = ['name', 'staff_id', 'status', 'last_login_at'];

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', User::class);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::enum(UserStatus::class)],
            'org_unit' => ['nullable', 'integer', 'exists:org_units,id'],
            'role' => ['nullable', 'string', 'exists:roles,name'],
            'sort' => ['nullable', Rule::in(self::SORTS)],
            'dir' => ['nullable', 'in:asc,desc'],
        ]);

        $users = User::query()
            ->with(['orgUnit', 'position', 'roles'])
            ->search($filters['search'] ?? null)
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['org_unit'] ?? null, fn ($q, $unit) => $q->inUnitTree((int) $unit))
            ->when($filters['role'] ?? null, fn ($q, $role) => $q->role($role))
            ->orderBy($filters['sort'] ?? 'name', $filters['dir'] ?? 'asc')
            ->orderBy('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (User $u) => UserPayload::make($u, $request->user()));

        return Inertia::render('Users/Index', [
            'users' => $users,
            'filters' => (object) $filters,
            'orgUnits' => $this->orgUnitOptions(),
            'roles' => Role::orderBy('name')->get(['name', 'display_name']),
            'statuses' => array_column(UserStatus::cases(), 'value'),
            'can' => ['create' => $request->user()->can('create', User::class)],
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', User::class);

        return Inertia::render('Users/Create', $this->formOptions($request));
    }

    public function store(StoreUserRequest $request, CreateUser $create): RedirectResponse
    {
        $user = $create($request->user(), $request->validated());

        return redirect()->route('users.show', $user)->with('status', __('cas.users.invited', ['email' => $user->email]));
    }

    public function show(Request $request, User $user): Response
    {
        $this->authorize('view', $user);
        $user->load(['orgUnit', 'position', 'roles']);
        $actor = $request->user();

        return Inertia::render('Users/Show', [
            'user' => UserPayload::make($user, $actor),
            'assignments' => $user->assignments()->with(['orgUnit', 'position'])->latest('started_at')->latest('id')->get()->map(fn ($a) => [
                'id' => $a->id,
                'org_unit' => $a->orgUnit->name,
                'position' => $a->position?->title,
                'started_at' => $a->started_at->toDateString(),
                'ended_at' => $a->ended_at?->toDateString(),
            ]),
            'orgUnits' => $this->orgUnitOptions(),
            'positions' => Position::orderBy('title')->get(['id', 'title', 'org_unit_id']),
            'can' => [
                'update' => $actor->can('update', $user),
                'lock' => $actor->can('lock', $user) && ! $actor->is($user),
                'transfer' => $actor->can('transfer', $user),
                'invite' => $actor->can('invite', $user),
                'delete' => $actor->can('delete', $user) && ! $actor->is($user),
            ],
        ]);
    }

    public function edit(Request $request, User $user): Response
    {
        $this->authorize('update', $user);

        return Inertia::render('Users/Edit', ['user' => UserPayload::make($user->load(['orgUnit', 'position', 'roles']), $request->user())] + $this->formOptions($request));
    }

    public function update(UpdateUserRequest $request, User $user, UpdateUser $update): RedirectResponse
    {
        $update($request->user(), $user, $request->validated());

        return redirect()->route('users.show', $user)->with('status', __('cas.users.updated'));
    }

    public function destroy(Request $request, User $user, DeleteUser $delete): RedirectResponse
    {
        $this->authorize('delete', $user);
        $delete($request->user(), $user);

        return redirect()->route('users.index')->with('status', __('cas.users.deleted'));
    }

    /** @return array<string, mixed> */
    private function formOptions(Request $request): array
    {
        return [
            'orgUnits' => $this->orgUnitOptions(),
            'positions' => Position::orderBy('title')->get(['id', 'title', 'org_unit_id']),
            'roles' => app(AssignableRoles::class)($request->user())->map->only(['name', 'display_name'])->values(),
        ];
    }

    /** @return list<array{id: int, name: string, depth: int}> */
    private function orgUnitOptions(): array
    {
        return OrgUnit::outline();
    }
}
