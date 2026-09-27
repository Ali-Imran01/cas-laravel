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
use Illuminate\Support\Collection;
use Kalnoy\Nestedset\NodeTrait;

/**
 * @property int $id
 * @property string $name
 * @property int $_lft
 * @property int $_rgt
 */
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

    /**
     * Ids of a unit and everything below it, straight from the nested-set bounds.
     *
     * @return Collection<int, int>
     */
    public static function treeIds(int $id): Collection
    {
        $unit = static::query()->findOrFail($id);

        return static::query()->whereBetween('_lft', [$unit->_lft, $unit->_rgt])->pluck('id');
    }

    /**
     * Every unit in tree order with its depth, for indented pickers. One pass over the _lft ordering:
     * a stack of open ancestors' _rgt values gives the depth.
     *
     * @return list<array{id: int, name: string, depth: int}>
     */
    public static function outline(): array
    {
        $open = [];
        $rows = [];

        foreach (static::query()->orderBy('_lft')->get(['id', 'name', '_lft', '_rgt']) as $unit) {
            while ($open !== [] && end($open) < $unit->_lft) {
                array_pop($open);
            }
            $rows[] = ['id' => $unit->id, 'name' => $unit->name, 'depth' => count($open)];
            $open[] = $unit->_rgt;
        }

        return $rows;
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
