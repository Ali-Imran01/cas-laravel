<?php

namespace App\Http\Requests\Users;

use App\Domain\Identity\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransferUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('transfer', $this->route('user'));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var User $target */
        $target = $this->route('user');

        return [
            'org_unit_id' => [
                'required', 'integer', Rule::exists('org_units', 'id')->whereNull('deleted_at'),
                fn ($attr, $value, $fail) => (int) $value === $target->org_unit_id && (int) $this->input('position_id') === (int) $target->position_id
                    ? $fail(__('cas.users.same_assignment')) : null,
            ],
            'position_id' => ['nullable', 'integer', Rule::exists('positions', 'id')->where('org_unit_id', $this->input('org_unit_id'))],
            'started_at' => ['nullable', 'date', 'before_or_equal:today'],
        ];
    }
}
