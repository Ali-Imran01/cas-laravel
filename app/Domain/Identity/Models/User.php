<?php

namespace App\Domain\Identity\Models;

use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Organization\Models\OrgUnit;
use App\Domain\Organization\Models\Position;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Passport\Contracts\OAuthenticatable;
use Laravel\Passport\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $staff_id
 * @property string $name
 * @property string $email
 * @property string|null $password
 * @property UserStatus $status
 * @property Carbon|null $locked_until
 * @property int $failed_login_count
 * @property bool $mfa_enabled
 * @property string|null $mfa_secret
 * @property list<string>|null $mfa_recovery_codes
 * @property Carbon|null $password_changed_at
 * @property bool $must_change_password
 * @property Carbon|null $last_login_at
 */
#[UseFactory(UserFactory::class)]
class User extends Authenticatable implements OAuthenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable, SoftDeletes;

    protected $fillable = [
        'staff_id',
        'name',
        'email',
        'password',
        'org_unit_id',
        'position_id',
        'status',
        'must_change_password',
        'created_by',
    ];

    /** Security state (lockout, MFA, login tracking) is never mass-assignable. */
    protected $hidden = [
        'password',
        'remember_token',
        'mfa_secret',
        'mfa_recovery_codes',
    ];

    /** @var array<string, mixed> */
    protected $attributes = [
        'status' => 'pending',
    ];

    protected function casts(): array
    {
        return [
            'status' => UserStatus::class,
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'password_changed_at' => 'datetime',
            'locked_until' => 'datetime',
            'last_login_at' => 'datetime',
            'mfa_enabled' => 'boolean',
            'must_change_password' => 'boolean',
            'mfa_secret' => 'encrypted',
            'mfa_recovery_codes' => 'encrypted:array',
        ];
    }

    /**
     * Name, staff ID or email contains the term (case-insensitive; LIKE wildcards in the term are literal).
     *
     * @param  Builder<User>  $query
     */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        if ($term === null || trim($term) === '') {
            return;
        }

        $like = '%'.addcslashes(mb_strtolower(trim($term)), '\\%_').'%';
        $query->where(fn (Builder $q) => $q
            ->whereRaw('lower(name) like ?', [$like])
            ->orWhereRaw('lower(staff_id) like ?', [$like])
            ->orWhereRaw('lower(email) like ?', [$like]));
    }

    /**
     * Members of an org unit or any unit below it.
     *
     * @param  Builder<User>  $query
     */
    public function scopeInUnitTree(Builder $query, int $orgUnitId): void
    {
        $query->whereIn('org_unit_id', OrgUnit::treeIds($orgUnitId));
    }

    /** @return BelongsTo<OrgUnit, $this> */
    public function orgUnit(): BelongsTo
    {
        return $this->belongsTo(OrgUnit::class);
    }

    /** @return BelongsTo<Position, $this> */
    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(self::class, 'created_by');
    }

    /** @return HasMany<UserAssignment, $this> */
    public function assignments(): HasMany
    {
        return $this->hasMany(UserAssignment::class);
    }

    /** @return HasMany<PasswordHistory, $this> */
    public function passwordHistories(): HasMany
    {
        return $this->hasMany(PasswordHistory::class);
    }
}
