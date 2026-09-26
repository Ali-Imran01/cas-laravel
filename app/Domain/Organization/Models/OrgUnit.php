<?php

namespace App\Domain\Organization\Models;

use App\Domain\Identity\Models\User;
use App\Domain\Organization\Enums\OrgUnitType;
use Database\Factories\OrgUnitFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Kalnoy\Nestedset\NodeTrait;

#[UseFactory(OrgUnitFactory::class)]
class OrgUnit extends Model
{
    /** @use HasFactory<OrgUnitFactory> */
    use HasFactory, NodeTrait, SoftDeletes;

    protected $fillable = ['parent_id', 'type', 'code', 'name', 'head_user_id', 'cost_centre', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return [
            'type' => OrgUnitType::class,
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function head(): BelongsTo
    {
        return $this->belongsTo(User::class, 'head_user_id');
    }

    /** @return HasMany<Position, $this> */
    public function positions(): HasMany
    {
        return $this->hasMany(Position::class);
    }

    /** @return HasMany<User, $this> */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
