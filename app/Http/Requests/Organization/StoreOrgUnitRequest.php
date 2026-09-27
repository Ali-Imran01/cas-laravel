<?php

namespace App\Http\Requests\Organization;

use App\Domain\Organization\Enums\OrgUnitType;
use App\Domain\Organization\Models\OrgUnit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrgUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', OrgUnit::class);
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['code' => strtoupper(trim((string) $this->input('code')))]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'parent_id' => ['nullable', 'integer', Rule::exists('org_units', 'id')->whereNull('deleted_at')],
            'type' => ['required', Rule::enum(OrgUnitType::class)],
            // Unique across soft-deleted rows too: the column's unique index still holds them.
            'code' => ['required', 'string', 'max:20', 'regex:/^[A-Z0-9-]+$/', Rule::unique('org_units', 'code')],
            'name' => ['required', 'string', 'max:150'],
            'cost_centre' => ['nullable', 'string', 'max:30'],
        ];
    }
}
