<?php

namespace App\Http\Requests\Users;

use App\Domain\Identity\Models\User;

class UpdateUserRequest extends SaveUserRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->target());
    }

    protected function target(): User
    {
        /** @var User */
        return $this->route('user');
    }
}
