<?php

namespace App\Http\Controllers;

use App\Domain\Settings\Actions\UpdatePolicySettings;
use App\Domain\Settings\Models\Setting;
use App\Domain\Settings\Support\PolicySettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function index(Request $request, PolicySettings $settings): Response
    {
        $this->authorize('viewAny', Setting::class);

        return Inertia::render('Settings/Index', [
            'policies' => $settings->current(),
            'can' => ['update' => $request->user()->can('update', Setting::class)],
        ]);
    }

    public function updatePolicies(Request $request, UpdatePolicySettings $update): RedirectResponse
    {
        $this->authorize('update', Setting::class);

        $rules = collect(PolicySettings::FIELDS)->mapWithKeys(fn (array $rules, string $field) => [$field => array_merge(['required'], $rules)])->all();
        $data = $request->validate($rules);

        $update($data);

        return back()->with('status', __('cas.settings.updated'));
    }
}
