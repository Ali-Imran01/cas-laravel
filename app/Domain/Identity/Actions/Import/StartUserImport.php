<?php

namespace App\Domain\Identity\Actions\Import;

use App\Domain\Audit\Audit;
use App\Domain\Identity\Enums\ImportStatus;
use App\Domain\Identity\Jobs\ProcessUserImport;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\UserImport;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

class StartUserImport
{
    /** @throws ValidationException */
    public function __invoke(User $actor, UploadedFile $file): UserImport
    {
        $running = UserImport::query()->where('created_by', $actor->id)
            ->whereIn('status', [ImportStatus::Queued->value, ImportStatus::Processing->value])->exists();

        if ($running) {
            throw ValidationException::withMessages(['file' => __('cas.import.active_exists')]);
        }

        $import = UserImport::create([
            'file_path' => $file->store('imports', 'local'),
            'status' => ImportStatus::Queued,
            'created_by' => $actor->id,
        ]);

        Audit::record('IMPORT_START', 'Started a CSV user import', $import, new: ['file_name' => $file->getClientOriginalName(), 'size_bytes' => $file->getSize()]);

        ProcessUserImport::dispatch($import->id);

        return $import;
    }
}
