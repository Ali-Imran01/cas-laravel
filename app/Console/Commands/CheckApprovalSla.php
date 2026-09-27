<?php

namespace App\Console\Commands;

use App\Domain\Approvals\Actions\DecideRequest;
use App\Domain\Approvals\Enums\ApprovalStatus;
use App\Domain\Approvals\Handlers\HandlerRegistry;
use App\Domain\Approvals\Models\ApprovalRequest;
use App\Domain\Approvals\Notifications\ApprovalNeeded;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

class CheckApprovalSla extends Command
{
    protected $signature = 'approvals:check-sla';

    protected $description = 'Remind the current approvers of requests that have run past their SLA, once each';

    public function __construct(private readonly HandlerRegistry $handlers)
    {
        parent::__construct();
    }

    public function handle(DecideRequest $decide): int
    {
        $overdue = ApprovalRequest::query()->with(['workflow', 'requester'])
            ->where('status', ApprovalStatus::Pending)
            ->where('due_at', '<=', now())
            ->whereNull('overdue_notified_at')
            ->get();

        foreach ($overdue as $request) {
            $approvers = $decide->approvers($request);

            if ($approvers->isNotEmpty()) {
                Notification::send($approvers, new ApprovalNeeded($request, $this->summary($request), overdue: true));
            }

            $request->update(['overdue_notified_at' => now()]);
        }

        $this->info("Reminded approvers on {$overdue->count()} overdue requests.");

        return self::SUCCESS;
    }

    private function summary(ApprovalRequest $request): string
    {
        return $request->reference.': '.($this->handlers->for($request->workflow->code)?->describe($request->payload) ?? $request->workflow->name);
    }
}
