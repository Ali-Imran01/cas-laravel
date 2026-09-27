<?php

namespace App\Domain\Approvals\Models;

use App\Domain\Approvals\Enums\ApprovalStatus;
use App\Domain\Apps\Models\Application;
use App\Domain\Identity\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $reference
 * @property int $workflow_id
 * @property int $requester_id
 * @property string $subject_type
 * @property int|null $subject_id
 * @property array<string, mixed> $payload
 * @property list<array{level: int, name: string, approver_type: string, approver_role_id: int|null, approver_user_id: int|null, sla_hours: int}> $steps
 * @property string|null $justification
 * @property ApprovalStatus $status
 * @property int $current_level
 * @property int|null $source_application_id
 * @property Carbon|null $due_at
 * @property Carbon|null $overdue_notified_at
 * @property Carbon|null $completed_at
 */
class ApprovalRequest extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'steps' => 'array',
            'status' => ApprovalStatus::class,
            'due_at' => 'datetime',
            'overdue_notified_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<ApprovalWorkflow, $this> */
    public function workflow(): BelongsTo
    {
        return $this->belongsTo(ApprovalWorkflow::class, 'workflow_id');
    }

    /** @return BelongsTo<User, $this> */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id')->withTrashed();
    }

    /** @return BelongsTo<Application, $this> */
    public function sourceApplication(): BelongsTo
    {
        return $this->belongsTo(Application::class, 'source_application_id');
    }

    /** @return HasMany<ApprovalAction, $this> */
    public function actions(): HasMany
    {
        return $this->hasMany(ApprovalAction::class, 'request_id')->orderBy('id');
    }

    /**
     * @return array{level: int, name: string, approver_type: string, approver_role_id: int|null, approver_user_id: int|null, sla_hours: int}|null
     */
    public function currentStep(): ?array
    {
        return collect($this->steps)->firstWhere('level', $this->current_level);
    }

    public function isOverdue(): bool
    {
        return $this->status === ApprovalStatus::Pending && $this->due_at !== null && $this->due_at->isPast();
    }
}
