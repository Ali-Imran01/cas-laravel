<?php

namespace App\Http\Controllers\Users;

use App\Domain\Identity\Actions\SendInvite;
use App\Domain\Identity\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

class UserInviteController extends Controller
{
    public function store(User $user, SendInvite $invite): RedirectResponse
    {
        $this->authorize('invite', $user);
        $invite($user);

        return back()->with('status', __('cas.users.invite_resent', ['email' => $user->email]));
    }
}
