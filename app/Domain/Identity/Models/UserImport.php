<?php

namespace App\Domain\Identity\Models;

use App\Domain\Identity\Enums\ImportStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $file_path
 * @property ImportStatus $status
 * @property int $total_rows
 * @property int $success_rows
 * @property int $failed_rows
 * @property list<array{row: int|null, field: string, message: string}>|null $errors
 * @property int $created_by
 * @property Carbon|null $created_at
 */
class UserImport extends Model
{
    protected $fillable = ['file_path', 'status', 'total_rows', 'success_rows', 'failed_rows', 'errors', 'created_by'];

    protected function casts(): array
    {
        return [
            'status' => ImportStatus::class,
            'errors' => 'array',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
