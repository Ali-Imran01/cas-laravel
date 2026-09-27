<?php

namespace App\Http\Controllers\Organization;

use App\Domain\Organization\Models\OrgUnit;
use App\Domain\Organization\Models\Position;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organization\SavePositionRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class PositionController extends Controller
{
    public function store(SavePositionRequest $request, OrgUnit $unit): RedirectResponse
    {
        $unit->positions()->create($request->validated());

        return back()->with('status', __('cas.org.position_saved'));
    }

    public function update(SavePositionRequest $request, Position $position): RedirectResponse
    {
        $position->update($request->validated());

        return back()->with('status', __('cas.org.position_saved'));
    }

    public function destroy(Position $position): RedirectResponse
    {
        $this->authorize('update', OrgUnit::class);

        if ($position->users()->exists()) {
            throw ValidationException::withMessages(['position' => __('cas.org.position_in_use')]);
        }

        $position->delete();

        return back()->with('status', __('cas.org.position_deleted'));
    }
}
