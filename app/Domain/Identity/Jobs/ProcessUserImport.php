<?php

namespace App\Domain\Identity\Jobs;

use App\Domain\Identity\Actions\Import\ImportUsers;
use App\Domain\Identity\Enums\ImportStatus;
use App\Domain\Identity\Models\UserImport;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ProcessUserImport implements ShouldQueue
{
    use Queueable;

    /** A half-run import must not start over: rows already created would all fail as duplicates. */
    public int $tries = 1;

    public int $timeout = 900;

    public function __construct(public readonly int $importId) {}

    public function handle(ImportUsers $import): void
    {
        $model = UserImport::find($this->importId);

        if ($model && $model->status === ImportStatus::Queued) {
            $import($model);
        }
    }

    public function failed(Throwable $e): void
    {
        $import = UserImport::find($this->importId);

        if ($import) {
            $import->update(['status' => ImportStatus::Failed, 'errors' => [['row' => null, 'field' => 'file', 'message' => __('cas.import.unexpected')]]]);
            Storage::disk('local')->delete($import->file_path);
        }
    }
}
