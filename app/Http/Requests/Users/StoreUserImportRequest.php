<?php

namespace App\Http\Requests\Users;

use App\Domain\Identity\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class StoreUserImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', User::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['file' => ['required', 'file', 'mimes:csv,txt', 'max:'.config('cas.import.max_kb')]];
    }
}
