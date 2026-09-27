<?php

namespace App\Http\Requests\Users;

use App\Domain\Identity\Models\User;
use Illuminate\Validation\Rule;

class StoreUserRequest extends SaveUserRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', User::class);
    }

    protected function target(): ?User
    {
        return null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return parent::rules() + [
            'org_unit_id' => ['nullable', 'integer', Rule::exists('org_units', 'id')->whereNull('deleted_at')],
            // A position must belong to the chosen unit.
            'position_id' => ['nullable', 'integer', Rule::exists('positions', 'id')->where('org_unit_id', $this->input('org_unit_id'))],
        ];
    }
}
