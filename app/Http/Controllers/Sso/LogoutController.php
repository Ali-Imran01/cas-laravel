<?php

namespace App\Http\Controllers\Sso;

use App\Domain\Apps\Actions\RevokeAppTokens;
use App\Domain\Apps\Models\Application;
use App\Domain\Audit\Audit;
use App\Domain\Identity\Models\User;
use App\Domain\Sso\IdTokens;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/**
 * Single logout. An app can end a person's CAS session everywhere either from its server (POST with the
 * person's access token) or by sending their browser here with the ID token as a hint (GET).
 */
class LogoutController extends Controller
{
    /** Server-to-server: revokes every token the person holds and ends their CAS sessions. */
    public function api(Request $request, RevokeAppTokens $tokens): JsonResponse|Response
    {
        /** @var User $user */
        $user = $request->user('api');
        $this->endEverything($user, $tokens, 'api');

        return response()->noContent();
    }

    /** Browser: `id_token_hint` is required, so a random page cannot sign people out by embedding this address. */
    public function browser(Request $request, IdTokens $idTokens, RevokeAppTokens $tokens): RedirectResponse|\Symfony\Component\HttpFoundation\Response
    {
        $hint = $idTokens->parseOwn((string) $request->query('id_token_hint'));
        $clientId = $hint?->claims()->get('aud')[0] ?? null;
        $app = $clientId ? Application::query()->where('oauth_client_id', $clientId)->first() : null;
        $user = $hint ? User::query()->find($hint->claims()->get('sub')) : null;

        if ($app === null || $user === null) {
            return Inertia::render('OAuth/Denied', ['reason' => 'invalid_logout', 'app' => null])->toResponse($request)->setStatusCode(400);
        }

        $this->endEverything($user, $tokens, 'oidc');
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $target = $this->allowedRedirect($app, (string) $request->query('post_logout_redirect_uri'));

        if ($target === null) {
            return redirect()->route('login');
        }

        $state = (string) $request->query('state');

        return redirect()->away($state === '' ? $target : $target.(str_contains($target, '?') ? '&' : '?').http_build_query(['state' => $state]));
    }

    private function endEverything(User $user, RevokeAppTokens $tokens, string $via): void
    {
        DB::table('sessions')->where('user_id', $user->id)->delete();
        $tokens->forUser($user);
        Audit::record('LOGOUT', "{$user->staff_id} signed out everywhere", $user, new: ['via' => $via], actorId: $user->id);
    }

    /** Only an address on the same origin as one of the app's registered redirect URIs may be sent back to. */
    private function allowedRedirect(Application $app, string $uri): ?string
    {
        $origin = fn (string $u) => ($p = parse_url($u)) && isset($p['scheme'], $p['host'])
            ? strtolower($p['scheme'].'://'.$p['host'].(isset($p['port']) ? ':'.$p['port'] : ''))
            : null;

        $wanted = $origin($uri);

        return $wanted !== null && ! str_contains($uri, "\n") && collect($app->client->redirect_uris)->contains(fn ($registered) => $origin($registered) === $wanted)
            ? $uri
            : null;
    }
}
