<?php

namespace App\Domain\Approvals\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property bool $is_active
 * @property bool $allow_api
 */
class ApprovalWorkflow extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'allow_api' => 'boolean'];
    }

    /** @return HasMany<ApprovalWorkflowStep, $this> */
    public function steps(): HasMany
    {
        return $this->hasMany(ApprovalWorkflowStep::class, 'workflow_id')->orderBy('level');
    }
}
