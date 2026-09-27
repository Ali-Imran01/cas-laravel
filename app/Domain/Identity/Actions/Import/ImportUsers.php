<?php

namespace App\Domain\Identity\Actions\Import;

use App\Domain\Access\Actions\AssignableRoles;
use App\Domain\Identity\Actions\CreateUser;
use App\Domain\Identity\Enums\ImportStatus;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\UserImport;
use App\Domain\Identity\Rules\UserRules;
use App\Domain\Organization\Models\OrgUnit;
use App\Domain\Organization\Models\Position;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Throwable;

/**
 * Reads an uploaded CSV row by row and creates invited accounts through the same rules and action as the
 * create form. One bad row never stops the rest; the uploaded file (personal data) is deleted afterwards.
 */
class ImportUsers
{
    private const REQUIRED = ['staff_id', 'name', 'email'];

    private const OPTIONAL = ['org_unit_code', 'position_title', 'role'];

    public function __construct(private readonly CreateUser $create, private readonly AssignableRoles $assignable) {}

    public function __invoke(UserImport $import): void
    {
        $import->update(['status' => ImportStatus::Processing]);
        $disk = Storage::disk('local');

        try {
            $actor = $import->creator;

            if (! $actor || Gate::forUser($actor)->denies('create', User::class)) {
                $this->fail($import, 'not_allowed');
            } elseif (! $disk->exists($import->file_path)) {
                $this->fail($import, 'unreadable');
            } else {
                $this->process($import, $actor, $disk->path($import->file_path));
            }
        } finally {
            $disk->delete($import->file_path);
        }
    }

    private function process(UserImport $import, User $actor, string $path): void
    {
        $handle = fopen($path, 'r');
        $header = $handle ? $this->header(fgetcsv($handle)) : null;

        if ($header === null) {
            $this->fail($import, 'empty');

            return;
        }

        $missing = array_diff(self::REQUIRED, array_keys($header));
        if ($missing !== []) {
            $this->fail($import, 'missing_columns', ['columns' => implode(', ', $missing)]);

            return;
        }

        $units = OrgUnit::query()->pluck('id', 'code')->mapWithKeys(fn ($id, $code) => [strtoupper($code) => $id])->all();
        $allowedRoles = ($this->assignable)($actor)->pluck('name')->all();
        $rules = UserRules::base(null, $allowedRoles) + ['org_unit_code' => ['nullable', 'string', 'max:20'], 'position_title' => ['nullable', 'string', 'max:120']];

        $max = (int) config('cas.import.max_rows');
        $chunk = (int) config('cas.import.chunk');
        $errors = [];
        $dropped = 0;
        $seen = ['staff_id' => [], 'email' => []];
        $total = $ok = $bad = 0;
        $line = 1;

        while (($cells = fgetcsv($handle)) !== false) {
            $line++;

            if ($cells === [null] || count(array_filter($cells, fn ($c) => trim((string) $c) !== '')) === 0) {
                continue; // blank line
            }

            if ($total >= $max) {
                $this->addError($errors, $dropped, $line, 'file', __('cas.import.too_many_rows', ['max' => $max]));
                break;
            }

            $total++;
            $rowErrors = $this->row($actor, $this->assoc($header, $cells), $rules, $units, $seen);

            if ($rowErrors === []) {
                $ok++;
            } else {
                $bad++;
                foreach ($rowErrors as $field => $message) {
                    $this->addError($errors, $dropped, $line, $field, $message);
                }
            }

            if ($total % $chunk === 0) {
                $import->update(['total_rows' => $total, 'success_rows' => $ok, 'failed_rows' => $bad, 'errors' => $errors]);
            }
        }
        fclose($handle);

        if ($dropped > 0) {
            $errors[] = ['row' => null, 'field' => 'file', 'message' => __('cas.import.errors_truncated', ['count' => $dropped])];
        }

        $import->update([
            'status' => $total === 0 ? ImportStatus::Failed : ImportStatus::Completed,
            'total_rows' => $total, 'success_rows' => $ok, 'failed_rows' => $bad,
            'errors' => $total === 0 ? [['row' => null, 'field' => 'file', 'message' => __('cas.import.no_rows')]] : $errors,
        ]);
    }

    /**
     * @param  array<string, string|null>  $data
     * @param  array<string, mixed>  $rules
     * @param  array<string, int>  $units
     * @param  array{staff_id: array<string, true>, email: array<string, true>}  $seen
     * @return array<string, string> field => message; empty when the account was created
     */
    private function row(User $actor, array $data, array $rules, array $units, array &$seen): array
    {
        $validator = Validator::make($data, $rules);
        $errors = array_map(fn ($messages) => $messages[0], $validator->errors()->messages());

        foreach (['staff_id', 'email'] as $field) {
            if (isset($seen[$field][$data[$field]]) && ! isset($errors[$field])) {
                $errors[$field] = __('cas.import.duplicate_in_file');
            }
            $seen[$field][$data[$field]] = true;
        }

        $unitId = null;
        if ($data['org_unit_code'] !== null) {
            $unitId = $units[$data['org_unit_code']] ?? null;
            if ($unitId === null) {
                $errors['org_unit_code'] = __('cas.import.unknown_unit');
            }
        }

        $positionId = null;
        if ($data['position_title'] !== null && ! isset($errors['org_unit_code'])) {
            if ($unitId === null) {
                $errors['position_title'] = __('cas.import.position_needs_unit');
            } else {
                $positionId = Position::query()->where('org_unit_id', $unitId)->whereRaw('lower(title) = ?', [mb_strtolower($data['position_title'])])->value('id');
                if ($positionId === null) {
                    $errors['position_title'] = __('cas.import.unknown_position');
                }
            }
        }

        if ($errors !== []) {
            return $errors;
        }

        try {
            ($this->create)($actor, [
                'staff_id' => $data['staff_id'], 'name' => $data['name'], 'email' => $data['email'],
                'role' => $data['role'], 'org_unit_id' => $unitId, 'position_id' => $positionId,
            ]);
        } catch (Throwable $e) {
            report($e);

            // The account is saved before the invitation mail goes out: a mail failure must not count as a failed row.
            if (! User::query()->where('staff_id', $data['staff_id'])->exists()) {
                return ['file' => __('cas.import.unexpected')];
            }
        }

        return [];
    }

    /**
     * @param  array<int, string|null>|false  $cells
     * @return array<string, int>|null column name => index
     */
    private function header(array|false $cells): ?array
    {
        if ($cells === false || $cells === [null]) {
            return null;
        }

        $columns = [];
        foreach ($cells as $i => $cell) {
            $name = str_replace([' ', '-'], '_', strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $cell) ?? '')));
            if (in_array($name, [...self::REQUIRED, ...self::OPTIONAL], true)) {
                $columns[$name] = $i;
            }
        }

        return $columns;
    }

    /**
     * @param  array<string, int>  $header
     * @param  array<int, string|null>  $cells
     * @return array<string, string|null>
     */
    private function assoc(array $header, array $cells): array
    {
        $value = fn (string $name) => isset($header[$name]) ? trim((string) ($cells[$header[$name]] ?? '')) : '';
        $orNull = fn (string $s) => $s === '' ? null : $s;

        return [
            'staff_id' => strtoupper($value('staff_id')),
            'name' => $value('name'),
            'email' => strtolower($value('email')),
            'org_unit_code' => ($code = $orNull($value('org_unit_code'))) === null ? null : strtoupper($code),
            'position_title' => $orNull($value('position_title')),
            'role' => $orNull($value('role')),
        ];
    }

    /** @param list<array{row: int|null, field: string, message: string}> $errors */
    private function addError(array &$errors, int &$dropped, ?int $row, string $field, string $message): void
    {
        if (count($errors) >= config('cas.import.max_errors')) {
            $dropped++;

            return;
        }

        $errors[] = ['row' => $row, 'field' => $field, 'message' => $message];
    }

    /** @param array<string, int|string> $replace */
    private function fail(UserImport $import, string $key, array $replace = []): void
    {
        $import->update(['status' => ImportStatus::Failed, 'errors' => [['row' => null, 'field' => 'file', 'message' => __("cas.import.$key", $replace)]]]);
    }
}
