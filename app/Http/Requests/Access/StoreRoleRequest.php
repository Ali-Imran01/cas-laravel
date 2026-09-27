<?php

namespace App\Http\Requests\Access;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class StoreRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Role::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // The name is what claims and code refer to, so it is fixed once created.
            'name' => ['required', 'string', 'regex:/^[a-z][a-z0-9_]{1,48}$/', Rule::unique('roles', 'name')->where('guard_name', 'web')],
            'display_name' => ['required', 'string', 'max:125'],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }
}
