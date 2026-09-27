<?php

namespace App\Http\Requests\Organization;

use App\Domain\Organization\Models\OrgUnit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MoveOrgUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', OrgUnit::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['parent_id' => ['nullable', 'integer', Rule::exists('org_units', 'id')->whereNull('deleted_at')]];
    }
}
