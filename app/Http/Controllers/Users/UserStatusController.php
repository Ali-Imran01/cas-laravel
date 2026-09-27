<?php

namespace App\Http\Controllers\Users;

use App\Domain\Identity\Actions\ChangeUserStatus;
use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class UserStatusController extends Controller
{
    public function lock(Request $request, User $user, ChangeUserStatus $change): RedirectResponse
    {
        return $this->apply($request, $user, $change, 'lock', UserStatus::Locked, 'locked');
    }

    public function unlock(Request $request, User $user, ChangeUserStatus $change): RedirectResponse
    {
        return $this->apply($request, $user, $change, 'unlock', UserStatus::Active, 'unlocked');
    }

    public function deactivate(Request $request, User $user, ChangeUserStatus $change): RedirectResponse
    {
        return $this->apply($request, $user, $change, 'deactivate', UserStatus::Inactive, 'deactivated');
    }

    public function reactivate(Request $request, User $user, ChangeUserStatus $change): RedirectResponse
    {
        return $this->apply($request, $user, $change, 'reactivate', UserStatus::Active, 'reactivated');
    }

    private function apply(Request $request, User $user, ChangeUserStatus $change, string $ability, UserStatus $to, string $message): RedirectResponse
    {
        $this->authorize($ability, $user);
        $change($request->user(), $user, $to);

        return back()->with('status', __("cas.users.$message"));
    }
}
