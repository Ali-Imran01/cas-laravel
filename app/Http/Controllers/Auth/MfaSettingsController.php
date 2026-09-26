<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Identity\Actions\Mfa\EnableMfa;
use App\Domain\Identity\Actions\Mfa\Totp;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class MfaSettingsController extends Controller
{
    public function show(Request $request, Totp $totp): Response
    {
        $user = $request->user();
        $props = ['enabled' => $user->mfa_enabled];

        if (! $user->mfa_enabled) {
            // The secret lives only in the session until the user proves the app works.
            $secret = $request->session()->get('mfa_setup_secret') ?? $totp->generateSecret();
            $request->session()->put('mfa_setup_secret', $secret);
            $props += ['secret' => $secret, 'uri' => $totp->uri($user->email, $secret)];
        }

        return Inertia::render('MfaSetup', $props);
    }

    public function store(Request $request, EnableMfa $enable): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:16']]);
        $secret = $request->session()->get('mfa_setup_secret');

        $codes = $secret ? $enable($request->user(), $secret, $data['code']) : null;
        if ($codes === null) {
            throw ValidationException::withMessages(['code' => __('cas.auth.mfa_invalid')]);
        }

        $request->session()->forget('mfa_setup_secret');

        return redirect()->route('mfa.setup')->with('recoveryCodes', $codes)->with('status', 'mfa-enabled');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->validate(['current_password' => ['required', 'current_password']]);

        $request->user()->forceFill(['mfa_enabled' => false, 'mfa_secret' => null, 'mfa_recovery_codes' => null])->save();

        return redirect()->route('mfa.setup')->with('status', 'mfa-disabled');
    }
}
