<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;
use Spatie\Permission\Models\Permission;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'locale' => app()->getLocale(),
            'auth' => [
                'user' => $request->user()?->only(['id', 'staff_id', 'name', 'email', 'mfa_enabled']),
                // Used only to hide links and buttons; every route still checks permission on the server.
                'permissions' => fn () => $request->user() === null ? [] : (
                    $request->user()->hasRole('super_admin')
                        ? Permission::query()->pluck('name')->all()
                        : $request->user()->getAllPermissions()->pluck('name')->values()->all()
                ),
            ],
            // Read at render time, so flash data set by the previous request is still there.
            'flash' => fn () => [
                'status' => $request->session()->get('status'),
                'recoveryCodes' => $request->session()->get('recoveryCodes'),
            ],
        ];
    }
}
