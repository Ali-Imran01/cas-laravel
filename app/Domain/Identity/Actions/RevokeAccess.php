<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Apps\Actions\RevokeAppTokens;
use App\Domain\Identity\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Ends every signed-in session, remember-me cookie and app token of a user. */
class RevokeAccess
{
    public function __construct(private readonly RevokeAppTokens $appTokens) {}

    public function __invoke(User $user): void
    {
        DB::table('sessions')->where('user_id', $user->id)->delete();
        $user->forceFill(['remember_token' => Str::random(60)])->save();
        $this->appTokens->forUser($user);
    }
}
