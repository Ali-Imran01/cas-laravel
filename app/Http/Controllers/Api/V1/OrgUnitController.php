<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Organization\Models\OrgUnit;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/** The organization tree, flat and in tree order, with each unit's parent so apps can rebuild the hierarchy. */
class OrgUnitController extends Controller
{
    public function index(): JsonResponse
    {
        $units = OrgUnit::query()->with('head')->orderBy('_lft')->get();
        $depths = collect(OrgUnit::outline())->pluck('depth', 'id');

        return $this->json(['data' => $units->map(fn (OrgUnit $u) => $this->present($u, $depths[$u->id] ?? 0))->all()]);
    }

    public function show(int $id): JsonResponse
    {
        $unit = OrgUnit::query()->with('head')->find($id);

        if ($unit === null) {
            return $this->json(['error' => 'not_found'], 404);
        }

        $depth = collect(OrgUnit::outline())->firstWhere('id', $id)['depth'] ?? 0;

        return $this->json(['data' => $this->present($unit, $depth) + [
            'children' => OrgUnit::query()->where('parent_id', $id)->orderBy('_lft')->pluck('id')->all(),
        ]]);
    }

    /** @return array<string, mixed> */
    private function present(OrgUnit $unit, int $depth): array
    {
        return [
            'id' => $unit->id,
            'code' => $unit->code,
            'name' => $unit->name,
            'type' => $unit->type->value,
            'parent_id' => $unit->parent_id,
            'depth' => $depth,
            'is_active' => $unit->is_active,
            'head' => $unit->head ? ['id' => (string) $unit->head->id, 'staff_id' => $unit->head->staff_id, 'name' => $unit->head->name] : null,
        ];
    }

    /** @param array<string, mixed> $body */
    private function json(array $body, int $status = 200): JsonResponse
    {
        return response()->json($body, $status)->withHeaders(['Cache-Control' => 'private, no-store']);
    }
}
