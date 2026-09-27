<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Identity\Actions\AcceptInvite;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class AcceptInviteController extends Controller
{
    public function create(Request $request, string $token): Response
    {
        return Inertia::render('AcceptInvite', ['token' => $token, 'email' => (string) $request->query('email')]);
    }

    public function store(Request $request, AcceptInvite $accept): RedirectResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $accept($data + ['password_confirmation' => $request->input('password_confirmation')]);

        return redirect()->route('login')->with('status', __('cas.users.invite_accepted'));
    }
}
