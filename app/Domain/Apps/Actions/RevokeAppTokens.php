<?php

namespace App\Domain\Apps\Actions;

use App\Domain\Apps\Models\Application;
use App\Domain\Identity\Models\User;
use Laravel\Passport\Passport;

/**
 * Ends app sessions. Access tokens are short-lived, but refresh tokens would keep an app signed in for
 * weeks, so anything that should cut a person off from an app has to revoke both.
 */
class RevokeAppTokens
{
    public function __construct(private readonly AppAccessCheck $access) {}

    /** All of a person's tokens, optionally only for one app. Returns how many access tokens were revoked. */
    public function forUser(User $user, ?Application $app = null): int
    {
        $ids = Passport::token()->newQuery()
            ->where('user_id', $user->id)->where('revoked', false)
            ->when($app, fn ($q) => $q->where('client_id', $app->oauth_client_id))
            ->pluck('id');

        if ($ids->isEmpty()) {
            return 0;
        }

        Passport::refreshToken()->newQuery()->whereIn('access_token_id', $ids)->update(['revoked' => true]);

        return Passport::token()->newQuery()->whereIn('id', $ids)->update(['revoked' => true]);
    }

    /** Revokes tokens for exactly those apps the person can no longer sign in to (role changed, grant removed or expired). */
    public function forLostAccess(User $user): void
    {
        $clientIds = Passport::token()->newQuery()->where('user_id', $user->id)->where('revoked', false)->distinct()->pluck('client_id');

        foreach (Application::query()->whereIn('oauth_client_id', $clientIds)->get() as $app) {
            if ($this->access->denial($user, $app) !== null) {
                $this->forUser($user, $app);
            }
        }
    }
}
