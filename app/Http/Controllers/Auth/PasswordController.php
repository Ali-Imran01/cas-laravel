<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Identity\Actions\ChangePassword;
use App\Domain\Identity\Actions\PasswordExpiry;
use App\Domain\Identity\Rules\NotRecentlyUsed;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class PasswordController extends Controller
{
    public function edit(Request $request): Response
    {
        return Inertia::render('ChangePassword', ['forced' => PasswordExpiry::mustChange($request->user())]);
    }

    public function update(Request $request, ChangePassword $change): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::defaults(), new NotRecentlyUsed($user)],
        ]);

        $change($user, $data['password'], $request->session()->getId());

        return redirect()->route('dashboard')->with('status', 'password-changed');
    }
}
