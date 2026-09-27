<?php

namespace App\Domain\Approvals\Models;

use App\Domain\Approvals\Enums\ApprovalDecision;
use App\Domain\Identity\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $request_id
 * @property int $level
 * @property int $actor_id
 * @property ApprovalDecision $decision
 * @property string|null $comment
 * @property Carbon $created_at
 */
class ApprovalAction extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['decision' => ApprovalDecision::class, 'created_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id')->withTrashed();
    }
}
