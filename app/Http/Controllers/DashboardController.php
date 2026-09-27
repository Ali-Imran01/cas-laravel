<?php

namespace App\Http\Controllers;

use App\Domain\Approvals\Actions\DecideRequest;
use App\Domain\Approvals\Enums\ApprovalStatus;
use App\Domain\Approvals\Models\ApprovalRequest;
use App\Domain\Apps\Enums\AppStatus;
use App\Domain\Apps\Models\Application;
use App\Domain\Audit\Enums\AuthResult;
use App\Domain\Audit\Models\AuditLog;
use App\Domain\Audit\Models\LoginAttempt;
use App\Domain\Identity\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    private const DAYS = 14;

    public function __invoke(Request $request, DecideRequest $decide): Response
    {
        $from = today()->subDays(self::DAYS - 1);
        $perDay = LoginAttempt::query()
            ->where('result', AuthResult::Success->value)->where('created_at', '>=', $from)
            ->selectRaw('created_at::date as day, count(*) as total')->groupBy('day')->pluck('total', 'day');

        $signIns = collect(range(0, self::DAYS - 1))->map(function (int $i) use ($from, $perDay) {
            $day = $from->copy()->addDays($i)->toDateString();

            return ['date' => $day, 'count' => (int) ($perDay[$day] ?? 0)];
        })->all();

        return Inertia::render('Dashboard', [
            'kpis' => [
                'users' => User::query()->count(),
                'apps' => Application::query()->where('status', '!=', AppStatus::Disabled->value)->count(),
                'pendingApprovals' => ApprovalRequest::query()->where('status', ApprovalStatus::Pending)->get()
                    ->filter(fn (ApprovalRequest $r) => $decide->canDecide($request->user(), $r))->count(),
                'signInsToday' => end($signIns)['count'],
            ],
            'signIns' => $signIns,
            // Activity feed only for people allowed to read the audit log.
            'recent' => $request->user()->can('viewAny', AuditLog::class)
                ? AuditLog::query()->with('actor')->latest('id')->limit(6)->get()->map(fn (AuditLog $a) => [
                    'id' => $a->id,
                    'created_at' => $a->created_at->toIso8601String(),
                    'actor' => $a->actor?->name,
                    'description' => $a->description,
                    'result' => $a->result->value,
                ])->all()
                : null,
        ]);
    }
}
