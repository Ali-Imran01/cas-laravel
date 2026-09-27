<?php

namespace App\Http\Controllers\Sso;

use App\Domain\Sso\OidcKeys;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/** The public key apps use to verify ID and access tokens. Public by design. */
class JwksController extends Controller
{
    public function __invoke(OidcKeys $keys): JsonResponse
    {
        return response()->json(['keys' => [$keys->jwk()]])
            ->withHeaders(['Cache-Control' => 'public, max-age=300', 'Access-Control-Allow-Origin' => '*']);
    }
}
