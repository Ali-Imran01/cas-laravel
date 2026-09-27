<?php

namespace App\Domain\Approvals\Listeners;

use App\Domain\Approvals\Events\ApprovalCompleted;
use App\Domain\Approvals\Jobs\SendApprovalWebhook;

/** Only a request a connected app submitted through the API has anyone waiting on a webhook. */
class DispatchApprovalWebhook
{
    public function handle(ApprovalCompleted $event): void
    {
        if ($event->request->source_application_id !== null) {
            SendApprovalWebhook::dispatch($event->request->id);
        }
    }
}
