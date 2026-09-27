<?php

namespace App\Http\Controllers\Organization;

use App\Domain\Audit\Audit;
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
        $position = $unit->positions()->create($request->validated());
        Audit::record('CREATE', "Created position {$position->title} in {$unit->code}", $position, [], $request->validated() + ['org_unit_id' => $unit->id]);

        return back()->with('status', __('cas.org.position_saved'));
    }

    public function update(SavePositionRequest $request, Position $position): RedirectResponse
    {
        $before = $position->only(['title', 'grade', 'headcount']);
        $position->update($request->validated());

        [$old, $new] = Audit::diff($before, $position->only(['title', 'grade', 'headcount']));
        if ($new !== []) {
            Audit::record('UPDATE', "Updated position {$position->title}", $position, $old, $new);
        }

        return back()->with('status', __('cas.org.position_saved'));
    }

    public function destroy(Position $position): RedirectResponse
    {
        $this->authorize('update', OrgUnit::class);

        if ($position->users()->exists()) {
            throw ValidationException::withMessages(['position' => __('cas.org.position_in_use')]);
        }

        $position->delete();
        Audit::record('DELETE', "Deleted position {$position->title}", $position, ['title' => $position->title, 'org_unit_id' => $position->org_unit_id]);

        return back()->with('status', __('cas.org.position_deleted'));
    }
}
