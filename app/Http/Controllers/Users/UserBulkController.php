<?php

namespace App\Http\Controllers\Users;

use App\Domain\Identity\Actions\ChangeUserStatus;
use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class UserBulkController extends Controller
{
    private const ACTIONS = ['lock' => UserStatus::Locked, 'unlock' => UserStatus::Active, 'deactivate' => UserStatus::Inactive];

    public function store(Request $request, ChangeUserStatus $change): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', 'in:'.implode(',', array_keys(self::ACTIONS))],
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['integer', 'distinct'],
        ]);

        $done = $skipped = 0;

        // Each account is authorized and checked on its own; one refusal never blocks the rest.
        foreach (User::whereKey($data['ids'])->get() as $user) {
            if (Gate::forUser($request->user())->denies($data['action'], $user)) {
                $skipped++;

                continue;
            }

            try {
                $change($request->user(), $user, self::ACTIONS[$data['action']]);
                $done++;
            } catch (ValidationException) {
                $skipped++;
            }
        }

        return back()->with('status', __('cas.users.bulk_result', ['done' => $done, 'skipped' => $skipped]));
    }
}
