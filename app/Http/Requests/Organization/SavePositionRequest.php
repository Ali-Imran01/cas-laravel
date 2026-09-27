<?php

namespace App\Http\Requests\Organization;

use App\Domain\Organization\Models\OrgUnit;
use App\Domain\Organization\Models\Position;
use Illuminate\Foundation\Http\FormRequest;

/** Create (route has {unit}) and update (route has {position}). */
class SavePositionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', OrgUnit::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var Position|null $position */
        $position = $this->route('position');
        $filled = $position?->users()->count() ?? 0;

        return [
            'title' => ['required', 'string', 'max:120'],
            'grade' => ['nullable', 'string', 'max:10'],
            // Cannot shrink below the people already in the post.
            'headcount' => ['required', 'integer', 'min:'.max(1, $filled), 'max:9999'],
        ];
    }
}
