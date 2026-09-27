<?php

namespace App\Domain\Audit\Models;

use App\Domain\Audit\Enums\AuthResult;
use App\Domain\Identity\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * One row per change or notable event. Append-only: the database rejects UPDATE and DELETE, and the
 * model refuses them earlier with a clearer error.
 *
 * @property int $id
 * @property int|null $actor_id
 * @property string $action
 * @property string|null $auditable_type
 * @property int|null $auditable_id
 * @property string $description
 * @property array<string, mixed>|null $old_values
 * @property array<string, mixed>|null $new_values
 * @property AuthResult $result
 * @property string|null $ip_address
 * @property Carbon $created_at
 * @property-read User|null $actor null for System events
 */
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'result' => AuthResult::class,
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id')->withTrashed();
    }

    protected function performUpdate(Builder $query): bool
    {
        throw new LogicException('Audit logs are append-only.');
    }

    protected function performDeleteOnModel(): void
    {
        throw new LogicException('Audit logs are append-only.');
    }
}
