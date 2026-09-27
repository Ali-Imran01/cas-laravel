<?php

namespace App\Domain\Sso;

use Carbon\CarbonImmutable;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use Lcobucci\JWT\Token\Plain;
use Lcobucci\JWT\Validation\Constraint\IssuedBy;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use Throwable;

/** Signs OpenID Connect ID tokens (RS256) and checks ones CAS signed earlier. */
class IdTokens
{
    public function __construct(private readonly OidcKeys $keys) {}

    /**
     * @param  array<string, mixed>  $claims  from UserClaims, must include `sub`
     */
    public function issue(string $clientId, array $claims, ?string $nonce, ?int $authTime): string
    {
        $now = CarbonImmutable::now();
        $builder = $this->config()->builder()
            ->issuedBy(Issuer::url())
            ->permittedFor($clientId)
            ->relatedTo((string) $claims['sub'])
            ->issuedAt($now)
            ->expiresAt($now->addMinutes((int) config('cas.sso.id_token_minutes')))
            ->withHeader('kid', $this->keys->kid())
            ->withClaim('azp', $clientId);

        if ($authTime !== null) {
            $builder = $builder->withClaim('auth_time', $authTime);
        }
        if ($nonce !== null) {
            $builder = $builder->withClaim('nonce', $nonce);
        }
        foreach ($claims as $name => $value) {
            if ($name !== 'sub' && $value !== null) {
                $builder = $builder->withClaim($name, $value);
            }
        }

        $config = $this->config();

        return $builder->getToken($config->signer(), $config->signingKey())->toString();
    }

    /**
     * Reads an ID token CAS issued, e.g. the `id_token_hint` of a logout request. The signature and issuer
     * must be ours; expiry is deliberately not checked, because hints are usually presented after expiry.
     */
    public function parseOwn(string $jwt): ?Plain
    {
        try {
            $config = $this->config();
            $token = $config->parser()->parse($jwt);

            $valid = $token instanceof Plain
                && $config->validator()->validate($token, new SignedWith($config->signer(), $config->verificationKey()), new IssuedBy(Issuer::url()));

            return $valid ? $token : null;
        } catch (Throwable) {
            return null;
        }
    }

    private function config(): Configuration
    {
        return Configuration::forAsymmetricSigner(
            new Sha256,
            InMemory::plainText($this->keys->privateKey()),
            InMemory::plainText($this->keys->publicKey()),
        );
    }
}
