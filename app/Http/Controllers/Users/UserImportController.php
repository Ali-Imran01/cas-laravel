<?php

namespace App\Http\Controllers\Users;

use App\Domain\Identity\Actions\Import\StartUserImport;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\UserImport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Users\StoreUserImportRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UserImportController extends Controller
{
    private const SHOWN_ERRORS = 200;

    public function create(Request $request): Response
    {
        $this->authorize('create', User::class);

        return Inertia::render('Users/Import', [
            'imports' => UserImport::query()->where('created_by', $request->user()->id)->latest('id')->limit(10)->get()
                ->map(fn (UserImport $i) => $this->summary($i))->all(),
            'maxKb' => config('cas.import.max_kb'),
            'maxRows' => config('cas.import.max_rows'),
        ]);
    }

    public function store(StoreUserImportRequest $request, StartUserImport $start): RedirectResponse
    {
        $import = $start($request->user(), $request->file('file'));

        return redirect()->route('users.imports.show', $import);
    }

    public function show(UserImport $import): Response
    {
        $this->authorize('view', $import);
        $errors = $import->errors ?? [];

        return Inertia::render('Users/ImportShow', [
            'import' => $this->summary($import) + ['errors' => array_slice($errors, 0, self::SHOWN_ERRORS), 'errors_total' => count($errors)],
        ]);
    }

    public function errors(UserImport $import): StreamedResponse
    {
        $this->authorize('view', $import);

        return response()->streamDownload(function () use ($import) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['row', 'field', 'message']);
            foreach ($import->errors ?? [] as $e) {
                fputcsv($out, [$e['row'] ?? '', $this->safe($e['field']), $this->safe($e['message'])]);
            }
            fclose($out);
        }, "import-{$import->id}-errors.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function template(): StreamedResponse
    {
        $this->authorize('create', User::class);

        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['staff_id', 'name', 'email', 'org_unit_code', 'position_title', 'role']);
            fputcsv($out, ['STF-90001', 'Example Person', 'example.person@example.com', 'ICT-APD', 'Systems Analyst', 'staff']);
            fclose($out);
        }, 'users-import-template.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** @return array<string, mixed> */
    private function summary(UserImport $import): array
    {
        return [
            'id' => $import->id,
            'status' => $import->status->value,
            'total_rows' => $import->total_rows,
            'success_rows' => $import->success_rows,
            'failed_rows' => $import->failed_rows,
            'created_at' => $import->created_at?->toIso8601String(),
        ];
    }

    /** Stops a spreadsheet from running a cell as a formula when someone opens the report. */
    private function safe(string $cell): string
    {
        return $cell !== '' && str_contains("=+-@\t\r", $cell[0]) ? "'".$cell : $cell;
    }
}
