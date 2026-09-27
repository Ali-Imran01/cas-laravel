<?php

namespace App\Domain\Approvals\Actions;

use App\Domain\Approvals\Enums\ApprovalDecision;
use App\Domain\Approvals\Enums\ApprovalStatus;
use App\Domain\Approvals\Handlers\HandlerRegistry;
use App\Domain\Approvals\Models\ApprovalRequest;
use App\Domain\Approvals\Models\ApprovalWorkflow;
use App\Domain\Approvals\Notifications\ApprovalNeeded;
use App\Domain\Apps\Models\Application;
use App\Domain\Audit\Audit;
use App\Domain\Identity\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SubmitRequest
{
    private const EXTERNAL_PAYLOAD_LIMIT = 10240; // bytes of JSON

    public function __construct(private readonly HandlerRegistry $handlers, private readonly ApproverResolver $resolver) {}

    /**
     * @param  array<string, mixed>  $payload
     * @param  array{type?: string, id?: int|null}  $subject  only for external workflows (a connected app's own kind of request)
     *
     * @throws ValidationException
     */
    public function __invoke(User $requester, ApprovalWorkflow $workflow, array $payload, ?string $justification = null, ?Application $source = null, array $subject = []): ApprovalRequest
    {
        if (! $workflow->is_active) {
            throw ValidationException::withMessages(['workflow' => __('cas.approvals.workflow_inactive')]);
        }

        $steps = $workflow->steps()->get()->map(fn ($s) => [
            'level' => $s->level, 'name' => $s->name, 'approver_type' => $s->approver_type->value,
            'approver_role_id' => $s->approver_role_id, 'approver_user_id' => $s->approver_user_id, 'sla_hours' => $s->sla_hours,
        ])->all();

        if ($steps === []) {
            throw ValidationException::withMessages(['workflow' => __('cas.approvals.no_steps')]);
        }

        [$payload, $subjectType, $subjectId] = $this->prepare($requester, $workflow, $payload, $subject);

        if ($this->resolver->resolve($steps[0], $requester, $payload)->isEmpty()) {
            throw ValidationException::withMessages(['workflow' => __('cas.approvals.no_approver', ['step' => $steps[0]['name']])]);
        }

        $request = DB::transaction(function () use ($requester, $workflow, $payload, $justification, $source, $steps, $subjectType, $subjectId) {
            $request = ApprovalRequest::create([
                'reference' => 'TMP'.Str::random(12),
                'workflow_id' => $workflow->id,
                'requester_id' => $requester->id,
                'subject_type' => $subjectType,
                'subject_id' => $subjectId,
                'payload' => $payload,
                'steps' => $steps,
                'justification' => $justification,
                'status' => ApprovalStatus::Pending,
                'current_level' => 1,
                'source_application_id' => $source?->id,
                'due_at' => now()->addHours($steps[0]['sla_hours']),
            ]);
            $request->update(['reference' => 'REQ-'.str_pad((string) $request->id, 4, '0', STR_PAD_LEFT)]);

            $request->actions()->create(['level' => 1, 'actor_id' => $requester->id, 'decision' => ApprovalDecision::Submitted, 'comment' => $justification]);
            Audit::record('SUBMIT', "Submitted {$request->reference}: {$this->describe($workflow, $payload)}", $request, [], ['workflow' => $workflow->code, 'payload' => $payload]);

            return $request;
        });

        $this->notifyApprovers($request, $steps[0], $requester, $payload, $this->describe($workflow, $payload));

        return $request;
    }

    /**
     * Normalizes and validates the payload for the workflow, and says what it is about.
     *
     * @param  array<string, mixed>  $payload
     * @param  array{type?: string, id?: int|null}  $subject
     * @return array{0: array<string, mixed>, 1: string, 2: int|null}
     */
    private function prepare(User $requester, ApprovalWorkflow $workflow, array $payload, array $subject): array
    {
        $handler = $this->handlers->for($workflow->code);

        if ($handler === null) {
            // A connected app's own workflow: CAS does not interpret the payload, only carries it and reports the outcome.
            if (strlen((string) json_encode($payload)) > self::EXTERNAL_PAYLOAD_LIMIT) {
                throw ValidationException::withMessages(['payload' => __('cas.approvals.payload_too_large')]);
            }

            return [$payload, Str::limit((string) ($subject['type'] ?? 'External'), 100, ''), $subject['id'] ?? null];
        }

        $payload = $handler->normalize($payload);
        $validator = Validator::make($payload, $handler->rules($requester, $payload));

        if ($validator->fails()) {
            // Report field problems under "payload.*" so a form can show them next to the right input.
            throw ValidationException::withMessages(collect($validator->errors()->messages())->mapWithKeys(fn ($m, $k) => ["payload.$k" => $m])->all());
        }

        if (! $handler->canRequest($requester, $payload)) {
            throw ValidationException::withMessages(['workflow' => __('cas.approvals.cannot_request')]);
        }

        [$type, $id] = $handler->subject($payload);

        return [$payload, $type, $id];
    }

    /** @param array<string, mixed> $payload */
    private function describe(ApprovalWorkflow $workflow, array $payload): string
    {
        return $this->handlers->for($workflow->code)?->describe($payload) ?? $workflow->name;
    }

    /**
     * @param  array<string, mixed>  $step
     * @param  array<string, mixed>  $payload
     */
    private function notifyApprovers(ApprovalRequest $request, array $step, User $requester, array $payload, string $summary): void
    {
        Notification::send($this->resolver->resolve($step, $requester, $payload), new ApprovalNeeded($request, $summary));
    }
}
