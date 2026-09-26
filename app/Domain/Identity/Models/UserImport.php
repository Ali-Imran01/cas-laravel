<?php

namespace App\Domain\Identity\Models;

use App\Domain\Identity\Enums\ImportStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
