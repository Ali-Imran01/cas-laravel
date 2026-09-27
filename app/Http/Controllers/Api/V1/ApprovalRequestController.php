<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Approvals\Actions\SubmitRequest;
use App\Domain\Approvals\Handlers\HandlerRegistry;
use App\Domain\Approvals\Models\ApprovalRequest;
use App\Domain\Approvals\Models\ApprovalWorkflow;
use App\Domain\Apps\Models\Application;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Lets a connected app carry its own kind of request through CAS's approval engine, on behalf of the
 * person behind its access token. Only workflows an administrator has explicitly opened to apps accept
 * these; a built-in workflow never does, however its `allow_api` flag is set (see UpdateWorkflow).
 */
class ApprovalRequestController extends Controller
{
    public function __construct(private readonly HandlerRegistry $handlers) {}

    public function store(Request $request, SubmitRequest $submit): JsonResponse
    {
        $data = $request->validate([
            'workflow' => ['required', 'string', 'max:40'],
            'payload' => ['present', 'array'],
            'justification' => ['nullable', 'string', 'max:1000'],
            'subject_type' => ['nullable', 'string', 'max:100'],
            'subject_id' => ['nullable', 'integer'],
        ]);

        $workflow = ApprovalWorkflow::query()->where('code', $data['workflow'])->where('is_active', true)->where('allow_api', true)
            ->whereNotIn('code', $this->handlers->codes())->first();
        if ($workflow === null) {
            return $this->json(['error' => 'unknown_workflow', 'message' => __('cas.approvals.unknown_workflow')], 422);
        }

        /** @var Application $app */
        $app = $request->attributes->get('cas.app');
        $created = $submit($request->user('api'), $workflow, $data['payload'], $data['justification'] ?? null, $app, [
            'type' => $data['subject_type'] ?? null, 'id' => $data['subject_id'] ?? null,
        ]);

        return $this->json(['data' => $this->present($created)], 201);
    }

    public function show(Request $request, string $reference): JsonResponse
    {
        /** @var Application $app */
        $app = $request->attributes->get('cas.app');
        $found = ApprovalRequest::query()->with('workflow')->where('reference', $reference)->where('source_application_id', $app->id)->first();

        return $found ? $this->json(['data' => $this->present($found)]) : $this->json(['error' => 'not_found'], 404);
    }

    /** @return array<string, mixed> */
    private function present(ApprovalRequest $r): array
    {
        return [
            'id' => (string) $r->id,
            'reference' => $r->reference,
            'workflow' => $r->workflow->code,
            'status' => $r->status->value,
            'current_level' => $r->current_level,
            'levels' => count($r->steps),
            'payload' => $r->payload,
            'justification' => $r->justification,
            'due_at' => $r->due_at?->toIso8601String(),
            'completed_at' => $r->completed_at?->toIso8601String(),
            'created_at' => $r->created_at->toIso8601String(),
        ];
    }

    /** @param array<string, mixed> $body */
    private function json(array $body, int $status = 200): JsonResponse
    {
        return response()->json($body, $status)->withHeaders(['Cache-Control' => 'private, no-store']);
    }
}
