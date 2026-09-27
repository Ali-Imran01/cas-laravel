<?php

namespace App\Http\Controllers\Sso;

use App\Domain\Apps\Actions\AppAccessCheck;
use App\Domain\Apps\Actions\RevokeAppTokens;
use App\Domain\Apps\Models\Application;
use App\Domain\Identity\Models\User;
use App\Domain\Sso\IdTokens;
use App\Domain\Sso\UserClaims;
use Illuminate\Support\Facades\Cache;
use Laravel\Passport\Http\Controllers\AccessTokenController;
use League\OAuth2\Server\AuthorizationServer;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Passport's token endpoint, plus two things it does not do: it re-checks access on every code redemption
 * and refresh (so a person who lost access cannot keep renewing), and it adds an OpenID Connect ID token.
 */
class OidcTokenController extends AccessTokenController
{
    public function __construct(
        AuthorizationServer $server,
        private readonly IdTokens $idTokens,
        private readonly UserClaims $claims,
        private readonly AppAccessCheck $access,
        private readonly RevokeAppTokens $revoke,
    ) {
        parent::__construct($server);
    }

    public function issueToken(ServerRequestInterface $psrRequest, ResponseInterface $psrResponse): Response
    {
        $response = parent::issueToken($psrRequest, $psrResponse);
        $body = json_decode((string) $response->getContent(), true);
        $payload = $this->jwtPayload($body['access_token'] ?? null);

        if ($response->getStatusCode() !== 200 || $payload === null) {
            return $response;
        }

        $clientId = (string) (is_array($payload['aud'] ?? null) ? $payload['aud'][0] : ($payload['aud'] ?? ''));
        $app = Application::query()->where('oauth_client_id', $clientId)->first();
        $user = User::query()->find($payload['sub'] ?? null);

        if ($app === null || $user === null || $this->access->denial($user, $app) !== null) {
            // Access ended since the token was last issued: undo what Passport just did and refuse.
            if ($user && $app) {
                $this->revoke->forUser($user, $app);
            }

            return response()->json(['error' => 'invalid_grant', 'error_description' => 'The user no longer has access to this application.'], 400);
        }

        $scopes = $payload['scopes'] ?? [];
        if (in_array('openid', $scopes, true)) {
            $code = $psrRequest->getParsedBody()['code'] ?? null;
            // The nonce was bound to the code when it was issued; it applies to the first ID token only.
            $nonce = is_string($code) ? Cache::pull('oidc:nonce:'.hash('sha256', $code)) : null;

            $body['id_token'] = $this->idTokens->issue($clientId, $this->claims->for($user, $app, $scopes), $nonce, $user->last_login_at?->timestamp);
            $response->setContent(json_encode($body, JSON_THROW_ON_ERROR));
        }

        return $response;
    }

    /** @return array<string, mixed>|null the claims of a JWT we just issued ourselves (not a signature check) */
    private function jwtPayload(?string $jwt): ?array
    {
        $parts = explode('.', (string) $jwt);
        $json = count($parts) === 3 ? base64_decode(strtr($parts[1], '-_', '+/')) : false;

        return $json ? json_decode($json, true) : null;
    }
}
