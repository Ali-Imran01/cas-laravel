<?php

namespace App\Domain\Sso;

use App\Domain\Apps\Actions\AppAccessCheck;
use App\Domain\Apps\Models\Application;
use App\Domain\Identity\Models\User;

/** What CAS tells an app about a person, limited to the scopes that were granted. Used for the ID token and /userinfo alike. */
class UserClaims
{
    public function __construct(private readonly AppAccessCheck $access) {}

    /**
     * @param  list<string>  $scopes
     * @return array<string, mixed>
     */
    public function for(User $user, Application $app, array $scopes): array
    {
        $claims = ['sub' => (string) $user->id];

        if (in_array('profile', $scopes, true)) {
            $user->loadMissing(['orgUnit', 'position']);
            $claims += [
                'name' => $user->name,
                'staff_id' => $user->staff_id,
                'preferred_username' => $user->staff_id,
                'org_unit' => $user->orgUnit?->name,
                'org_unit_code' => $user->orgUnit?->code,
                'position' => $user->position?->title,
                'app_role' => $this->access->appRole($user, $app),
            ];
        }

        if (in_array('email', $scopes, true)) {
            $claims += ['email' => $user->email, 'email_verified' => $user->email_verified_at !== null];
        }

        return $claims;
    }
}
