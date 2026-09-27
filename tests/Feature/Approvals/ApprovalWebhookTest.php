<?php

use App\Domain\Approvals\Events\ApprovalCompleted;
use App\Domain\Approvals\Jobs\SendApprovalWebhook;
use App\Domain\Approvals\Listeners\DispatchApprovalWebhook;
use App\Domain\Approvals\Models\ApprovalRequest;
use App\Domain\Approvals\Models\ApprovalWorkflow;
use App\Domain\Apps\Actions\VerifyWebhookTarget;
use App\Domain\Identity\Models\User;
use Database\Seeders\AccessSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(AccessSeeder::class));

// A minimal, already-decided request row; what actually approved it is not the point of these tests.
function approval_row(array $over = []): ApprovalRequest
{
    $workflow = ApprovalWorkflow::create(['code' => 'row_test_'.uniqid(), 'name' => 'Row test', 'is_active' => true]);
    $requester = User::factory()->create();

    return ApprovalRequest::create($over + [
        'reference' => 'REQ-'.random_int(1000, 9999), 'workflow_id' => $workflow->id, 'requester_id' => $requester->id,
        'subject_type' => 'External', 'subject_id' => null, 'payload' => ['x' => 1],
        'steps' => [['level' => 1, 'name' => 'A', 'approver_type' => 'role', 'approver_role_id' => null, 'approver_user_id' => null, 'sla_hours' => 48]],
        'status' => 'approved', 'current_level' => 1, 'completed_at' => now(),
    ]);
}

it('allows a public https address, and https to localhost for local development', function () {
    $verify = app(VerifyWebhookTarget::class);

    expect($verify('https://8.8.8.8/hook'))->toBeTrue()->and($verify('https://127.0.0.1/hook'))->toBeTrue();
});

it('refuses plain http to a real host, credentials, a fragment and private or link-local addresses', function () {
    $verify = app(VerifyWebhookTarget::class);

    expect($verify('http://8.8.8.8/hook'))->toBeFalse()
        ->and($verify('https://user:pass@8.8.8.8/hook'))->toBeFalse()
        ->and($verify('https://8.8.8.8/hook#x'))->toBeFalse()
        ->and($verify('https://10.0.0.5/hook'))->toBeFalse()
        ->and($verify('https://172.16.0.5/hook'))->toBeFalse()
        ->and($verify('https://192.168.1.5/hook'))->toBeFalse()
        ->and($verify('https://169.254.169.254/hook'))->toBeFalse(); // a cloud metadata endpoint, never a legitimate webhook target
});

it('queues the webhook only for a request a connected app submitted, never one made on the web', function () {
    Queue::fake();
    [$app] = sso_app(['webhook_url' => 'https://8.8.8.8/hook', 'webhook_secret' => 'shh']);
    $fromApp = approval_row(['source_application_id' => $app->id]);
    $fromWeb = approval_row(['source_application_id' => null]);

    (new DispatchApprovalWebhook)->handle(new ApprovalCompleted($fromApp));
    (new DispatchApprovalWebhook)->handle(new ApprovalCompleted($fromWeb));

    Queue::assertPushed(SendApprovalWebhook::class, 1);
    Queue::assertPushed(SendApprovalWebhook::class, fn (SendApprovalWebhook $job) => $job->requestId === $fromApp->id);
});

it('signs the delivered body with the app secret and reports the outcome', function () {
    Http::fake();
    [$app] = sso_app(['webhook_url' => 'https://8.8.8.8/hook', 'webhook_secret' => 'top-secret']);
    $request = approval_row(['source_application_id' => $app->id, 'subject_type' => 'Ticket', 'subject_id' => 7, 'status' => 'approved']);

    (new SendApprovalWebhook($request->id))->handle(app(VerifyWebhookTarget::class));

    Http::assertSent(function ($sent) use ($request) {
        $body = json_decode($sent->body(), true);
        $signature = 'sha256='.hash_hmac('sha256', $sent->body(), 'top-secret');

        return (string) $sent->url() === 'https://8.8.8.8/hook'
            && $sent->hasHeader('X-CAS-Signature', $signature)
            && $body['event'] === 'approval.completed' && $body['reference'] === $request->reference
            && $body['subject_type'] === 'Ticket' && $body['subject_id'] === 7 && $body['status'] === 'approved';
    });
});

it('never calls out when the target address fails the safety check, even if it was once valid', function () {
    Http::fake();
    [$app] = sso_app(['webhook_url' => 'https://10.0.0.5/hook', 'webhook_secret' => 'shh']); // e.g. saved before it started resolving somewhere private
    $request = approval_row(['source_application_id' => $app->id]);

    (new SendApprovalWebhook($request->id))->handle(app(VerifyWebhookTarget::class));

    Http::assertNothingSent();
});

it('does nothing when the app has no webhook configured', function () {
    Http::fake();
    [$app] = sso_app();
    $request = approval_row(['source_application_id' => $app->id]);

    (new SendApprovalWebhook($request->id))->handle(app(VerifyWebhookTarget::class));

    Http::assertNothingSent();
});
