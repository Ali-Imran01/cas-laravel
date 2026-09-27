<?php

namespace App\Http\Controllers\Users;

use App\Domain\Identity\Actions\TransferUser;
use App\Domain\Identity\Models\User;
use App\Http\Controllers\Controller;
use App\Http\Requests\Users\TransferUserRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;

class UserTransferController extends Controller
{
    public function store(TransferUserRequest $request, User $user, TransferUser $transfer): RedirectResponse
    {
        $data = $request->validated();

        $transfer(
            $user,
            (int) $data['org_unit_id'],
            isset($data['position_id']) ? (int) $data['position_id'] : null,
            isset($data['started_at']) ? Carbon::parse($data['started_at']) : null,
        );

        return back()->with('status', __('cas.users.transferred'));
    }
}
