<?php

namespace App\Domain\Approvals\Models;

use App\Domain\Approvals\Enums\ApproverType;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $workflow_id
 * @property int $level
 * @property string $name
 * @property ApproverType $approver_type
 * @property int|null $approver_role_id
 * @property int|null $approver_user_id
 * @property int $sla_hours
 */
class ApprovalWorkflowStep extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['approver_type' => ApproverType::class];
    }
}
