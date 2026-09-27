<?php

namespace App\Domain\Approvals\Jobs;

use App\Domain\Approvals\Models\ApprovalRequest;
use App\Domain\Apps\Actions\VerifyWebhookTarget;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/** Tells the app that submitted a request, through its API, how it turned out. Never runs for a request submitted on the web. */
class SendApprovalWebhook implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public function __construct(public readonly int $requestId) {}

    /** @return list<int> seconds before each retry */
    public function backoff(): array
    {
        return [10, 60, 300, 900, 3600];
    }

    public function handle(VerifyWebhookTarget $isSafe): void
    {
        $request = ApprovalRequest::with(['workflow', 'sourceApplication'])->find($this->requestId);
        $app = $request?->sourceApplication;

        if ($request === null || $app === null || $app->webhook_url === null || $app->webhook_secret === null) {
            return; // nothing left to deliver to
        }

        // DNS can change between when the URL was saved and now, so this is checked again on every send.
        if (! $isSafe($app->webhook_url)) {
            Log::warning("Refused to call the webhook for app {$app->code}: the target address is not allowed.");

            return; // the URL itself is the problem, not a transient failure: retrying will not help
        }

        $body = json_encode([
            'event' => 'approval.completed',
            'reference' => $request->reference,
            'workflow' => $request->workflow->code,
            'status' => $request->status->value,
            'subject_type' => $request->subject_type,
            'subject_id' => $request->subject_id,
            'comment' => $request->actions()->latest('id')->value('comment'),
            'completed_at' => $request->completed_at?->toIso8601String(),
        ]);

        Http::withBody($body, 'application/json')
            ->withHeaders(['X-CAS-Event' => 'approval.completed', 'X-CAS-Signature' => 'sha256='.hash_hmac('sha256', $body, $app->webhook_secret)])
            ->timeout(5)
            ->throw()
            ->post($app->webhook_url);
    }
}
