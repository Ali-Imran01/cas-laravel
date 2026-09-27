<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Staff directory. Carries only what a directory needs: no security state, no login history, no roles. */
class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $params = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'org_unit' => ['nullable', 'integer', 'min:1'],
            // Active people by default; `inactive` lets an app notice who left so it can remove them too.
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'updated_since' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $status = ($params['status'] ?? 'active') === 'active' ? [UserStatus::Active->value] : [UserStatus::Inactive->value];

        $page = User::query()
            ->with(['orgUnit', 'position'])
            ->whereIn('status', $status)
            ->search($params['search'] ?? null)
            ->when($params['org_unit'] ?? null, fn ($q, $unit) => $q->inUnitTree((int) $unit))
            ->when($params['updated_since'] ?? null, fn ($q, $since) => $q->where('updated_at', '>=', $since))
            ->orderBy('id')
            ->paginate($params['per_page'] ?? 25)
            ->withQueryString();

        return $this->json([
            'data' => $page->getCollection()->map(fn (User $u) => $this->present($u))->all(),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'per_page' => $page->perPage(), 'total' => $page->total()],
            'links' => ['next' => $page->nextPageUrl(), 'prev' => $page->previousPageUrl()],
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $user = User::query()->with(['orgUnit', 'position'])->whereIn('status', [UserStatus::Active->value, UserStatus::Inactive->value])->find($id);

        return $user ? $this->json(['data' => $this->present($user)]) : $this->json(['error' => 'not_found'], 404);
    }

    /** @return array<string, mixed> */
    private function present(User $user): array
    {
        return [
            'id' => (string) $user->id, // the same value as `sub` in ID tokens
            'staff_id' => $user->staff_id,
            'name' => $user->name,
            'email' => $user->email,
            'active' => $user->status === UserStatus::Active,
            'org_unit' => $user->orgUnit ? ['id' => $user->orgUnit->id, 'code' => $user->orgUnit->code, 'name' => $user->orgUnit->name] : null,
            'position' => $user->position ? ['id' => $user->position->id, 'title' => $user->position->title, 'grade' => $user->position->grade] : null,
            'updated_at' => $user->updated_at?->toIso8601String(),
        ];
    }

    /** @param array<string, mixed> $body */
    private function json(array $body, int $status = 200): JsonResponse
    {
        return response()->json($body, $status)->withHeaders(['Cache-Control' => 'private, no-store']);
    }
}
