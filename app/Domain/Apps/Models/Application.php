<?php

namespace App\Domain\Apps\Models;

use App\Domain\Apps\Enums\AppEnvironment;
use App\Domain\Apps\Enums\AppStatus;
use App\Domain\Identity\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Role;

/**
 * An app that signs in through CAS. Wraps the Passport OAuth client that holds the credentials.
 *
 * @property int $id
 * @property string $oauth_client_id
 * @property string $code
 * @property string $name
 * @property AppEnvironment $environment
 * @property AppStatus $status
 * @property string|null $homepage_url
 * @property string|null $color
 * @property list<string> $allowed_scopes
 * @property int|null $owner_user_id
 * @property Carbon|null $secret_rotated_at
 * @property Carbon|null $disabled_at
 * @property string|null $webhook_url
 * @property string|null $webhook_secret
 */
class Application extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'environment' => AppEnvironment::class,
            'status' => AppStatus::class,
            'allowed_scopes' => 'array',
            'secret_rotated_at' => 'datetime',
            'disabled_at' => 'datetime',
            // Unlike the client secret (hashed, one-way), CAS needs this again for every webhook it signs, so it is kept, encrypted at rest.
            'webhook_secret' => 'encrypted',
        ];
    }

    /** @return BelongsTo<OAuthClient, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(OAuthClient::class, 'oauth_client_id');
    }

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    /** @return BelongsToMany<Role, $this> */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'application_role')->withPivot('app_role');
    }

    /** @return BelongsToMany<User, $this> */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'application_user')->withPivot(['app_role', 'granted_by', 'expires_at', 'created_at']);
    }
}
