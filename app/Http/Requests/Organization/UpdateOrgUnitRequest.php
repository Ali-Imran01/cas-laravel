<?php

namespace App\Http\Requests\Organization;

use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Models\User;
use App\Domain\Organization\Enums\OrgUnitType;
use App\Domain\Organization\Models\OrgUnit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrgUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', OrgUnit::class);
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['code' => strtoupper(trim((string) $this->input('code')))]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var OrgUnit $unit */
        $unit = $this->route('unit');

        return [
            'type' => ['required', Rule::enum(OrgUnitType::class)],
            'code' => ['required', 'string', 'max:20', 'regex:/^[A-Z0-9-]+$/', Rule::unique('org_units', 'code')->ignore($unit->id)],
            'name' => ['required', 'string', 'max:150'],
            'cost_centre' => ['nullable', 'string', 'max:30'],
            'is_active' => ['required', 'boolean'],
            // The head is an active member of the unit or of a unit below it.
            'head_user_id' => ['nullable', 'integer', function (string $attribute, mixed $value, \Closure $fail) use ($unit) {
                $ok = User::query()->whereKey($value)->where('status', UserStatus::Active->value)->inUnitTree($unit->id)->exists();
                if (! $ok) {
                    $fail(__('cas.org.head_not_member'));
                }
            }],
        ];
    }
}
