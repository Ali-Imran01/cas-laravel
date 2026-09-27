<?php

namespace App\Domain\Apps\Models;

use Illuminate\Contracts\Auth\Authenticatable;
use Laravel\Passport\Client;
use Laravel\Passport\Scope;

/**
 * Every app registered in CAS is a first-party, internal application: signing in through CAS is the
 * consent, so users are not asked to approve each app. Who may use an app is decided by CAS access rules.
 */
/** @property list<string> $redirect_uris */
class OAuthClient extends Client
{
    /** @param array<int, Scope> $scopes */
    public function skipsAuthorization(Authenticatable $user, array $scopes): bool
    {
        return true;
    }
}
