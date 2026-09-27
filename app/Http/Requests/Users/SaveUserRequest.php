<?php

namespace App\Http\Requests\Users;

use App\Domain\Access\Actions\AssignableRoles;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Rules\UserRules;
use Illuminate\Foundation\Http\FormRequest;

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

        return UserRules::base($target, $allowed);
    }
}
