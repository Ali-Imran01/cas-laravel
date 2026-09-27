<?php

namespace App\Http\Requests\Access;

use Illuminate\Foundation\Http\FormRequest;
use Spatie\Permission\Models\Role;

/** Used for both the details form and the permission grid (`permissions` is only present for the grid). */
class UpdateRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', Role::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'display_name' => ['required', 'string', 'max:125'],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }
}
