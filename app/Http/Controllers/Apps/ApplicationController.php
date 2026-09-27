<?php

namespace App\Http\Controllers\Apps;

use App\Domain\Apps\Actions\AppAccess;
use App\Domain\Apps\Actions\DeleteApplication;
use App\Domain\Apps\Actions\RegisterApplication;
use App\Domain\Apps\Actions\RotateClientSecret;
use App\Domain\Apps\Actions\RotateWebhookSecret;
use App\Domain\Apps\Actions\SetApplicationStatus;
use App\Domain\Apps\Actions\SetWebhook;
use App\Domain\Apps\Actions\UpdateApplication;
use App\Domain\Apps\Models\Application;
use App\Domain\Apps\Rules\RedirectUri;
use App\Domain\Identity\Models\User;
use App\Domain\Sso\Issuer;
use App\Http\Controllers\Controller;
use App\Http\Requests\Apps\SaveApplicationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class ApplicationController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Application::class);

        $apps = Application::query()->withCount('users')->orderBy('name')->get();
        $selected = $apps->firstWhere('id', (int) $request->query('app')) ?? $apps->first();

        return Inertia::render('Apps/Index', [
            'apps' => $apps->map(fn (Application $a) => [
                'id' => $a->id, 'code' => $a->code, 'name' => $a->name, 'environment' => $a->environment->value,
                'status' => $a->status->value, 'color' => $a->color,
            ])->all(),
            'selected' => $selected ? $this->detail($selected) : null,
            'scopes' => $this->scopeCatalog(),
            'defaultScopes' => config('cas.sso.default_scopes'),
            'roles' => Role::query()->orderBy('name')->get(['id', 'name'])->map(fn (Role $r) => ['id' => $r->id, 'name' => $r->name, 'display_name' => $r->getAttribute('display_name')])->all(),
            'endpoints' => Issuer::endpoints(),
            'can' => [
                'create' => $request->user()->can('create', Application::class),
                'update' => $request->user()->can('update', Application::class),
                'delete' => $request->user()->can('delete', Application::class),
            ],
        ]);
    }

    public function store(SaveApplicationRequest $request, RegisterApplication $register): RedirectResponse
    {
        [$app, $secret] = $register($request->user(), $request->validated());

        return redirect()->route('apps.index', ['app' => $app->id])
            ->with('status', __('cas.apps.registered'))
            ->with('appSecret', $secret);
    }

    public function update(SaveApplicationRequest $request, Application $app, UpdateApplication $update): RedirectResponse
    {
        $update($app, $request->validated());

        return back()->with('status', __('cas.apps.updated'));
    }

    public function rotateSecret(Request $request, Application $app, RotateClientSecret $rotate): RedirectResponse
    {
        $this->authorize('update', Application::class);

        return back()->with('status', __('cas.apps.secret_rotated'))->with('appSecret', $rotate($app));
    }

    public function disable(Application $app, SetApplicationStatus $status): RedirectResponse
    {
        $this->authorize('update', Application::class);
        $status->disable($app);

        return back()->with('status', __('cas.apps.disabled'));
    }

    public function enable(Application $app, SetApplicationStatus $status): RedirectResponse
    {
        $this->authorize('update', Application::class);
        $status->enable($app);

        return back()->with('status', __('cas.apps.enabled'));
    }

    public function destroy(Application $app, DeleteApplication $delete): RedirectResponse
    {
        $this->authorize('delete', Application::class);
        $delete($app);

        return redirect()->route('apps.index')->with('status', __('cas.apps.deleted'));
    }

    public function mapRole(Request $request, Application $app, AppAccess $access): RedirectResponse
    {
        $this->authorize('update', Application::class);
        $data = $request->validate([
            'role_id' => ['required', 'integer', 'exists:roles,id'],
            'app_role' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9 _.-]+$/'],
        ]);

        $access->mapRole($app, Role::query()->findOrFail($data['role_id']), $data['app_role']);

        return back()->with('status', __('cas.apps.access_saved'));
    }

    public function unmapRole(Application $app, Role $role, AppAccess $access): RedirectResponse
    {
        $this->authorize('update', Application::class);
        $access->unmapRole($app, $role);

        return back()->with('status', __('cas.apps.access_saved'));
    }

    public function grantUser(Request $request, Application $app, AppAccess $access): RedirectResponse
    {
        $this->authorize('update', Application::class);
        $data = $request->validate([
            'staff_id' => ['required', 'string', 'max:20'],
            'app_role' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9 _.-]+$/'],
            'expires_at' => ['nullable', 'date_format:Y-m-d', 'after:today'],
        ]);

        $user = User::query()->whereRaw('upper(staff_id) = ?', [strtoupper(trim($data['staff_id']))])->first();
        if ($user === null) {
            throw ValidationException::withMessages(['staff_id' => __('cas.apps.unknown_staff')]);
        }

        $access->grantUser($request->user(), $app, $user, $data['app_role'], isset($data['expires_at']) ? Carbon::parse($data['expires_at'])->endOfDay() : null);

        return back()->with('status', __('cas.apps.access_saved'));
    }

    public function revokeUser(Application $app, User $user, AppAccess $access): RedirectResponse
    {
        $this->authorize('update', Application::class);
        $access->revokeUser($app, $user);

        return back()->with('status', __('cas.apps.access_saved'));
    }

    public function updateWebhook(Request $request, Application $app, SetWebhook $set): RedirectResponse
    {
        $this->authorize('update', Application::class);
        $data = $request->validate(['webhook_url' => ['nullable', 'string', 'max:255', new RedirectUri]]);

        $secret = $set($app, $data['webhook_url'] ?? null);
        $redirect = back()->with('status', __($data['webhook_url'] === null ? 'cas.apps.webhook_cleared' : 'cas.apps.webhook_saved'));

        return $secret === null ? $redirect : $redirect->with('webhookSecret', $secret);
    }

    public function rotateWebhookSecret(Application $app, RotateWebhookSecret $rotate): RedirectResponse
    {
        $this->authorize('update', Application::class);

        return back()->with('status', __('cas.apps.webhook_secret_rotated'))->with('webhookSecret', $rotate($app));
    }

    /** @return list<array{name: string, description: string}> */
    private function scopeCatalog(): array
    {
        $catalog = [];
        foreach (config('cas.sso.scopes') as $name => $description) {
            $catalog[] = ['name' => $name, 'description' => $description];
        }

        return $catalog;
    }

    /** @return array<string, mixed> */
    private function detail(Application $app): array
    {
        $client = $app->client;

        return [
            'id' => $app->id,
            'code' => $app->code,
            'name' => $app->name,
            'environment' => $app->environment->value,
            'status' => $app->status->value,
            'homepage_url' => $app->homepage_url,
            'color' => $app->color,
            'allowed_scopes' => $app->allowed_scopes,
            'redirect_uris' => $client->redirect_uris,
            'client_id' => $client->getKey(),
            'secret_rotated_at' => $app->secret_rotated_at?->toIso8601String(),
            'owner' => $app->owner?->name,
            'webhook_url' => $app->webhook_url,
            'webhook_configured' => $app->webhook_secret !== null,
            'roles' => $app->roles()->orderBy('name')->get()->map(fn (Role $r) => [
                'id' => $r->id, 'name' => $r->name, 'app_role' => $r->getRelation('pivot')->getAttribute('app_role'),
            ])->all(),
            'grants' => $app->users()->orderBy('name')->get()->map(fn (User $u) => [
                'id' => $u->id, 'name' => $u->name, 'staff_id' => $u->staff_id, 'app_role' => $u->getRelation('pivot')->getAttribute('app_role'),
                'expires_at' => $u->getRelation('pivot')->getAttribute('expires_at'),
            ])->all(),
        ];
    }
}
