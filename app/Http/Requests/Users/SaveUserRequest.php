<?php

namespace App\Http\Requests\Users;

use App\Domain\Access\Actions\AssignableRoles;
use App\Domain\Identity\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Shared rules for creating and editing a user. Subclasses decide who is authorized. */
abstract class SaveUserRequest extends FormRequest
{
    /** The user being edited, or null when creating. */
    abstract protected function target(): ?User;

    protected function prepareForValidation(): void
    {
        $this->merge([
            'staff_id' => strtoupper(trim((string) $this->input('staff_id'))),
            'email' => strtolower(trim((string) $this->input('email'))),
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $target = $this->target();
        $assignable = app(AssignableRoles::class)($this->user())->pluck('name');
        // Leaving a role as it is must stay possible even when the actor could not hand it out.
        $allowed = $assignable->merge($target?->getRoleNames() ?? [])->unique()->values()->all();

        return [
            'staff_id' => ['required', 'string', 'max:20', 'regex:/^[A-Z0-9-]+$/', Rule::unique('users', 'staff_id')->ignore($target?->id)],
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email:rfc', 'max:191', Rule::unique('users', 'email')->ignore($target?->id)],
            'role' => ['nullable', 'string', Rule::in($allowed)],
        ];
    }
}
