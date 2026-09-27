<?php

namespace App\Http\Controllers\Sso;

use App\Domain\Apps\Actions\AppAccessCheck;
use App\Domain\Apps\Models\Application;
use App\Domain\Sso\UserClaims;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** The signed-in person's claims, for the scopes their access token carries. Also re-checks that they may still use the app. */
class UserInfoController extends Controller
{
    public function __invoke(Request $request, UserClaims $claims, AppAccessCheck $access): JsonResponse
    {
        $user = $request->user('api');
        $token = $user->currentAccessToken();

        if (! $user->tokenCan('openid')) {
            return $this->error('insufficient_scope', 403);
        }

        $app = Application::query()->where('oauth_client_id', $token->client_id ?? null)->first();

        if ($app === null || $access->denial($user, $app) !== null) {
            return $this->error('invalid_token', 401);
        }

        return response()->json($claims->for($user, $app, $token->scopes ?? []))
            ->withHeaders(['Cache-Control' => 'no-store', 'Access-Control-Allow-Origin' => '*']);
    }

    private function error(string $error, int $status): JsonResponse
    {
        return response()->json(['error' => $error], $status)
            ->withHeaders(['WWW-Authenticate' => 'Bearer error="'.$error.'"', 'Access-Control-Allow-Origin' => '*']);
    }
}
