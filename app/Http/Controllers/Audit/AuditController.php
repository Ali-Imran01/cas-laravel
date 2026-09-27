<?php

namespace App\Http\Controllers\Audit;

use App\Domain\Audit\Audit;
use App\Domain\Audit\AuditQueries;
use App\Domain\Audit\Enums\AuthResult;
use App\Domain\Audit\Enums\LoginMethod;
use App\Domain\Audit\Models\AuditLog;
use App\Domain\Audit\Models\LoginAttempt;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditController extends Controller
{
    private const EXPORT_LIMIT = 50000;

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', AuditLog::class);

        $view = $request->query('view') === 'signins' ? 'signins' : 'log';
        $filters = $this->filters($request);

        if ($view === 'signins') {
            $rows = AuditQueries::signIns($filters)->paginate(25)->withQueryString()->through(fn (LoginAttempt $a) => [
                'id' => $a->id,
                'created_at' => $a->created_at->toIso8601String(),
                'identifier' => $a->identifier,
                'method' => $a->method->value,
                'result' => $a->result->value,
                'reason' => $a->failure_reason,
                'app' => $a->application?->name,
                'ip' => $a->ip_address,
            ]);
        } else {
            $rows = AuditQueries::log($filters)->paginate(25)->withQueryString()->through(fn (AuditLog $a) => [
                'id' => $a->id,
                'created_at' => $a->created_at->toIso8601String(),
                'actor' => $a->actor ? ['name' => $a->actor->name, 'staff_id' => $a->actor->staff_id] : null,
                'action' => $a->action,
                'subject' => $a->auditable_type ? class_basename($a->auditable_type).' #'.$a->auditable_id : null,
                'description' => $a->description,
                'result' => $a->result->value,
                'ip' => $a->ip_address,
                'old_values' => $a->old_values,
                'new_values' => $a->new_values,
            ]);
        }

        return Inertia::render('Audit/Index', [
            'view' => $view,
            'entries' => $rows,
            'filters' => (object) $filters,
            'actions' => AuditLog::query()->distinct()->orderBy('action')->pluck('action'),
            'subjects' => AuditLog::query()->whereNotNull('auditable_type')->distinct()->pluck('auditable_type')
                ->map(fn (string $t) => class_basename($t))->unique()->sort()->values(),
            'results' => array_column(AuthResult::cases(), 'value'),
            'methods' => array_column(LoginMethod::cases(), 'value'),
            'exportQuery' => http_build_query($filters),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $this->authorize('viewAny', AuditLog::class);
        $filters = $this->filters($request);

        // The export is itself an audited event: who pulled which slice of the log, and when.
        Audit::record('EXPORT', 'Exported the audit log to CSV', null, [], ['filters' => $filters]);

        return response()->streamDownload(function () use ($filters) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['id', 'time', 'actor_staff_id', 'actor_name', 'action', 'subject', 'description', 'result', 'ip', 'old_values', 'new_values']);

            $written = 0;
            AuditQueries::log($filters)->reorder('id')->chunkById(1000, function ($chunk) use ($out, &$written) {
                foreach ($chunk as $a) {
                    fputcsv($out, array_map($this->safe(...), [
                        $a->id, $a->created_at->toIso8601String(), $a->actor->staff_id ?? '', $a->actor->name ?? 'System', $a->action,
                        $a->auditable_type ? class_basename($a->auditable_type).' #'.$a->auditable_id : '',
                        $a->description, $a->result->value, $a->ip_address ?? '',
                        $a->old_values ? json_encode($a->old_values) : '', $a->new_values ? json_encode($a->new_values) : '',
                    ]));

                    if (++$written >= self::EXPORT_LIMIT) {
                        return false;
                    }
                }
            });
            fclose($out);
        }, 'audit-log-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** @return array<string, string> only the filters that were actually given */
    private function filters(Request $request): array
    {
        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'action' => ['nullable', 'string', 'max:30'],
            'actor' => ['nullable', 'string', 'max:100'],
            'subject' => ['nullable', 'string', 'max:60'],
            'result' => ['nullable', Rule::enum(AuthResult::class)],
            'method' => ['nullable', Rule::enum(LoginMethod::class)],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        return array_filter($data, fn ($v) => $v !== null && $v !== '');
    }

    /** Stops a spreadsheet from running a cell as a formula when someone opens the export. */
    private function safe(mixed $cell): mixed
    {
        return is_string($cell) && $cell !== '' && str_contains("=+-@\t\r", $cell[0]) ? "'".$cell : $cell;
    }
}
