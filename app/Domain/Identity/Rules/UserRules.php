<?php

namespace App\Domain\Identity\Rules;

use App\Domain\Identity\Models\User;
use Illuminate\Validation\Rule;

/** Field rules shared by the create/edit forms and the CSV import, so both accept exactly the same users. */
class UserRules
{
    /**
     * @param  list<string>  $allowedRoles  role names the actor may assign
     * @return array<string, mixed>
     */
    public static function base(?User $target, array $allowedRoles): array
    {
        return [
            'staff_id' => ['required', 'string', 'max:20', 'regex:/^[A-Z0-9-]+$/', Rule::unique('users', 'staff_id')->ignore($target?->id)],
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email:rfc', 'max:191', Rule::unique('users', 'email')->ignore($target?->id)],
            'role' => ['nullable', 'string', Rule::in($allowedRoles)],
        ];
    }
}
