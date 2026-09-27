<?php

namespace App\Http\Controllers\Sso;

use App\Domain\Sso\Issuer;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/** OpenID Connect discovery: everything an app's OIDC library needs, from one address. */
class DiscoveryController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $url = Issuer::endpoints();

        return response()->json([
            'issuer' => $url['issuer'],
            'authorization_endpoint' => $url['authorization'],
            'token_endpoint' => $url['token'],
            'userinfo_endpoint' => $url['userinfo'],
            'jwks_uri' => $url['jwks'],
            'end_session_endpoint' => $url['end_session'],
            'response_types_supported' => ['code'],
            'response_modes_supported' => ['query'],
            'grant_types_supported' => ['authorization_code', 'refresh_token'],
            'subject_types_supported' => ['public'],
            'id_token_signing_alg_values_supported' => ['RS256'],
            'token_endpoint_auth_methods_supported' => ['client_secret_post', 'client_secret_basic'],
            'code_challenge_methods_supported' => ['S256'],
            'scopes_supported' => array_keys(config('cas.sso.scopes')),
            'claims_supported' => ['sub', 'iss', 'aud', 'exp', 'iat', 'auth_time', 'nonce', 'name', 'preferred_username', 'staff_id', 'org_unit', 'org_unit_code', 'position', 'app_role', 'email', 'email_verified'],
        ])->withHeaders(['Cache-Control' => 'public, max-age=3600', 'Access-Control-Allow-Origin' => '*']);
    }
}
