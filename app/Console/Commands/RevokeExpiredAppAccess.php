<?php

namespace App\Console\Commands;

use App\Domain\Apps\Actions\RevokeAppTokens;
use App\Domain\Identity\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RevokeExpiredAppAccess extends Command
{
    protected $signature = 'apps:revoke-expired';

    protected $description = 'Revoke app tokens of people whose time-limited app access has run out';

    public function handle(RevokeAppTokens $tokens): int
    {
        $userIds = DB::table('application_user')->where('expires_at', '<=', now())->distinct()->pluck('user_id');

        foreach (User::query()->whereIn('id', $userIds)->get() as $user) {
            $tokens->forLostAccess($user);
        }

        $this->info("Checked {$userIds->count()} people with expired grants.");

        return self::SUCCESS;
    }
}
