<?php

namespace App\Domain\Audit\Models;

use App\Domain\Apps\Models\Application;
use App\Domain\Audit\Enums\AuthResult;
use App\Domain\Audit\Enums\LoginMethod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $user_id
 * @property string $identifier
 * @property LoginMethod $method
 * @property AuthResult $result
 * @property string|null $failure_reason
 * @property int|null $application_id
 * @property string|null $ip_address
 * @property Carbon $created_at
 */
class LoginAttempt extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    /** @return BelongsTo<Application, $this> */
    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    protected function casts(): array
    {
        return [
            'method' => LoginMethod::class,
            'result' => AuthResult::class,
            'created_at' => 'datetime',
        ];
    }
}
