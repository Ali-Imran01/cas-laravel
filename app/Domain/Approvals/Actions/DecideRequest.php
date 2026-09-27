<?php

namespace App\Domain\Approvals\Actions;

use App\Domain\Approvals\Enums\ApprovalDecision;
use App\Domain\Approvals\Enums\ApprovalStatus;
use App\Domain\Approvals\Events\ApprovalCompleted;
use App\Domain\Approvals\Handlers\HandlerRegistry;
use App\Domain\Approvals\Models\ApprovalRequest;
use App\Domain\Approvals\Notifications\ApprovalNeeded;
use App\Domain\Approvals\Notifications\ApprovalOutcome;
use App\Domain\Audit\Audit;
use App\Domain\Audit\Enums\AuthResult;
use App\Domain\Identity\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Everything that can happen to a request after it was submitted. Each method takes a row lock first, so two
 * approvers deciding at once (or a decision racing a cancel) cannot both win.
 */
class DecideRequest
{
    public function __construct(private readonly HandlerRegistry $handlers, private readonly ApproverResolver $resolver) {}

    /**
     * Who can decide the request at its current level right now.
     *
     * @return Collection<int, User>
     */
    public function approvers(ApprovalRequest $request): Collection
    {
        $step = $request->currentStep();

        return $step === null || $request->status !== ApprovalStatus::Pending
            ? new Collection
            : $this->resolver->resolve($step, $request->requester, $request->payload);
    }

    public function canDecide(User $user, ApprovalRequest $request): bool
    {
        return $this->approvers($request)->contains('id', $user->id);
    }

    /**
     * Approve the current level; the last level puts the change into effect.
     *
     * @throws ValidationException
     */
    public function approve(User $actor, ApprovalRequest $request, ?string $comment = null): ApprovalRequest
    {
        return $this->decide($actor, $request, ApprovalDecision::Approved, $comment, function (ApprovalRequest $request) use ($actor) {
            $level = $request->current_level;

            if ($level < count($request->steps)) {
                $this->advance($request, $level + 1);
                Audit::record('APPROVE', "Approved level $level of {$request->reference}", $request, ['level' => $level], ['level' => $level + 1]);

                return;
            }

            $this->finish($actor, $request);
        });
    }

    /** @throws ValidationException */
    public function reject(User $actor, ApprovalRequest $request, string $comment): ApprovalRequest
    {
        $this->requireComment($comment);

        return $this->decide($actor, $request, ApprovalDecision::Rejected, $comment, function (ApprovalRequest $request) use ($comment) {
            $request->update(['status' => ApprovalStatus::Rejected, 'completed_at' => now(), 'due_at' => null]);
            Audit::record('REJECT', "Rejected {$request->reference}", $request, ['status' => 'pending'], ['status' => 'rejected', 'reason' => $comment], AuthResult::Failed);
            $request->requester->notify(new ApprovalOutcome($request, 'rejected', $this->summary($request), $comment));
            ApprovalCompleted::dispatch($request);
        });
    }

    /** Sends the request back to the requester with a question. The clock stops until they answer. @throws ValidationException */
    public function requestInfo(User $actor, ApprovalRequest $request, string $comment): ApprovalRequest
    {
        $this->requireComment($comment);

        return $this->decide($actor, $request, ApprovalDecision::InfoRequested, $comment, function (ApprovalRequest $request) use ($comment) {
            $request->update(['status' => ApprovalStatus::InfoRequested, 'due_at' => null]);
            Audit::record('REQUEST_INFO', "Asked for more information on {$request->reference}", $request, ['status' => 'pending'], ['status' => 'info_requested']);
            $request->requester->notify(new ApprovalOutcome($request, 'info_requested', $this->summary($request), $comment));
        });
    }

    /**
     * The requester answers a question, optionally correcting the request. It goes back to the same level.
     *
     * @param  array<string, mixed>|null  $payload  replacement payload (validated again)
     *
     * @throws ValidationException
     */
    public function resubmit(User $actor, ApprovalRequest $request, ?string $comment = null, ?array $payload = null): ApprovalRequest
    {
        return Audit::asActor($actor->id, fn () => DB::transaction(function () use ($actor, $request, $comment, $payload) {
            $request = $this->lock($request);

            if ($request->status !== ApprovalStatus::InfoRequested || $request->requester_id !== $actor->id) {
                throw ValidationException::withMessages(['request' => __('cas.approvals.cannot_resubmit')]);
            }

            if ($payload !== null) {
                $request->payload = $this->revalidated($request, $payload);
            }

            $step = $request->currentStep();
            $request->update(['status' => ApprovalStatus::Pending, 'payload' => $request->payload, 'due_at' => now()->addHours($step['sla_hours'] ?? 48), 'overdue_notified_at' => null]);
            $request->actions()->create(['level' => $request->current_level, 'actor_id' => $actor->id, 'decision' => ApprovalDecision::Resubmitted, 'comment' => $comment]);
            Audit::record('RESUBMIT', "Resubmitted {$request->reference}", $request, ['status' => 'info_requested'], ['status' => 'pending']);

            $approvers = $this->approvers($request);
            if ($approvers->isEmpty()) {
                throw ValidationException::withMessages(['request' => __('cas.approvals.no_approver', ['step' => $step['name'] ?? ''])]);
            }
            Notification::send($approvers, new ApprovalNeeded($request, $this->summary($request)));

            return $request;
        }));
    }

    /** Anyone involved may add a note while the request is open. @throws ValidationException */
    public function comment(User $actor, ApprovalRequest $request, string $comment): void
    {
        $this->requireComment($comment);
        $request->refresh();

        if (! $request->status->isOpen() || ! ($request->requester_id === $actor->id || $this->canDecide($actor, $request))) {
            throw ValidationException::withMessages(['request' => __('cas.approvals.cannot_comment')]);
        }

        $request->actions()->create(['level' => $request->current_level, 'actor_id' => $actor->id, 'decision' => ApprovalDecision::Commented, 'comment' => $comment]);
    }

    /** Only the requester withdraws, and only while it is still open. @throws ValidationException */
    public function cancel(User $actor, ApprovalRequest $request, ?string $comment = null): ApprovalRequest
    {
        return Audit::asActor($actor->id, fn () => DB::transaction(function () use ($actor, $request, $comment) {
            $request = $this->lock($request);

            if (! $request->status->isOpen() || $request->requester_id !== $actor->id) {
                throw ValidationException::withMessages(['request' => __('cas.approvals.cannot_cancel')]);
            }

            $request->update(['status' => ApprovalStatus::Cancelled, 'completed_at' => now(), 'due_at' => null]);
            $request->actions()->create(['level' => $request->current_level, 'actor_id' => $actor->id, 'decision' => ApprovalDecision::Cancelled, 'comment' => $comment]);
            Audit::record('CANCEL', "Cancelled {$request->reference}", $request, ['status' => 'pending'], ['status' => 'cancelled']);
            ApprovalCompleted::dispatch($request);

            return $request;
        }));
    }

    /**
     * Shared frame for approve / reject / request info: lock, check the actor may decide, record the action, run the effect.
     *
     * @param  callable(ApprovalRequest): void  $effect
     *
     * @throws ValidationException
     */
    private function decide(User $actor, ApprovalRequest $request, ApprovalDecision $decision, ?string $comment, callable $effect): ApprovalRequest
    {
        return Audit::asActor($actor->id, fn () => DB::transaction(function () use ($actor, $request, $decision, $comment, $effect) {
            $request = $this->lock($request);

            if ($request->status !== ApprovalStatus::Pending) {
                throw ValidationException::withMessages(['request' => __('cas.approvals.not_pending')]);
            }
            if (! $this->canDecide($actor, $request)) {
                throw ValidationException::withMessages(['request' => __('cas.approvals.not_approver')]);
            }

            $request->actions()->create(['level' => $request->current_level, 'actor_id' => $actor->id, 'decision' => $decision, 'comment' => $comment]);
            $effect($request);

            return $request->refresh();
        }));
    }

    /** @throws ValidationException when nobody can take the next level, so an approval never strands a request */
    private function advance(ApprovalRequest $request, int $level): void
    {
        $step = collect($request->steps)->firstWhere('level', $level);
        $next = $this->resolver->resolve($step, $request->requester, $request->payload);

        if ($next->isEmpty()) {
            throw ValidationException::withMessages(['request' => __('cas.approvals.no_approver', ['step' => $step['name']])]);
        }

        $request->update(['current_level' => $level, 'due_at' => now()->addHours($step['sla_hours']), 'overdue_notified_at' => null]);
        Notification::send($next, new ApprovalNeeded($request, $this->summary($request)));
    }

    /** Last level approved: the approver must be allowed to make the change themselves, then it is made. @throws ValidationException */
    private function finish(User $approver, ApprovalRequest $request): void
    {
        $handler = $this->handlers->for($request->workflow->code);

        if ($handler !== null) {
            // Days may have passed: the staff ID may be taken, the person gone, the expiry date past. Check again before acting.
            try {
                $this->revalidated($request, $request->payload);
            } catch (ValidationException) {
                throw ValidationException::withMessages(['request' => __('cas.approvals.stale')]);
            }

            if (! $handler->canApply($approver, $request->payload)) {
                throw ValidationException::withMessages(['request' => __('cas.approvals.cannot_apply')]);
            }

            $handler->apply($approver, $request);
        }

        $request->update(['status' => ApprovalStatus::Approved, 'completed_at' => now(), 'due_at' => null]);
        Audit::record('APPROVE', "Approved {$request->reference} (final)", $request, ['status' => 'pending'], ['status' => 'approved']);
        $request->requester->notify(new ApprovalOutcome($request, 'approved', $this->summary($request)));
        ApprovalCompleted::dispatch($request);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    private function revalidated(ApprovalRequest $request, array $payload): array
    {
        $handler = $this->handlers->for($request->workflow->code);

        if ($handler === null) {
            return $payload;
        }

        $payload = $handler->normalize($payload);
        $validator = Validator::make($payload, $handler->rules($request->requester, $payload));

        if ($validator->fails()) {
            throw ValidationException::withMessages(collect($validator->errors()->messages())->mapWithKeys(fn ($m, $k) => ["payload.$k" => $m])->all());
        }

        return $payload;
    }

    private function summary(ApprovalRequest $request): string
    {
        return $request->reference.': '.($this->handlers->for($request->workflow->code)?->describe($request->payload) ?? $request->workflow->name);
    }

    private function lock(ApprovalRequest $request): ApprovalRequest
    {
        return ApprovalRequest::query()->lockForUpdate()->findOrFail($request->id);
    }

    /** @throws ValidationException */
    private function requireComment(string $comment): void
    {
        if (trim($comment) === '') {
            throw ValidationException::withMessages(['comment' => __('cas.approvals.comment_required')]);
        }
    }
}
