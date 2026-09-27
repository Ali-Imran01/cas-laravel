<?php

namespace App\Domain\Settings\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $key
 * @property array<string, mixed>|null $value
 */
class Setting extends Model
{
    protected $primaryKey = 'key';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['value' => 'array'];
    }
}
