<?php

namespace App\Domain\Approvals\Handlers;

use App\Domain\Approvals\Models\ApprovalRequest;
use App\Domain\Identity\Models\User;

/**
 * What a kind of request means. The approval engine only moves a request through its levels; the handler
 * for the workflow's code says what the payload must look like and what happens when the last level approves.
 * Implementations inherit the parameter types documented here.
 */
interface WorkflowHandler
{
    public function code(): string;

    /**
     * Validation rules for the (normalized) payload; the payload is passed so rules can depend on other fields.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function rules(User $requester, array $payload): array;

    /**
     * May this person ask for this? (Asking is not the same as being allowed to do it: approvers decide that.)
     *
     * @param  array<string, mixed>  $payload
     */
    public function canRequest(User $requester, array $payload): bool;

    /**
     * What the request is about: a type name and an id.
     *
     * @param  array<string, mixed>  $payload
     * @return array{0: string, 1: int|null}
     */
    public function subject(array $payload): array;

    /**
     * Tidies the payload before it is validated and stored (e.g. upper-casing a staff ID).
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function normalize(array $payload): array;

    /**
     * One line for lists, e.g. "Change role of STF-10004 to hr_officer".
     *
     * @param  array<string, mixed>  $payload
     */
    public function describe(array $payload): string;

    /**
     * May this approver put the change into effect? Checked again at the last level, because they are the one executing it.
     *
     * @param  array<string, mixed>  $payload
     */
    public function canApply(User $approver, array $payload): bool;

    /** Puts the approved change into effect. Runs inside the approval transaction, so a failure leaves the request pending. */
    public function apply(User $approver, ApprovalRequest $request): void;
}
