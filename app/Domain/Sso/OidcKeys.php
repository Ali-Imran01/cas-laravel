<?php

namespace App\Domain\Sso;

use Laravel\Passport\Passport;
use RuntimeException;

/** The RSA key pair Passport signs access tokens with, reused to sign ID tokens and published as a JWK. */
final class OidcKeys
{
    public function privateKey(): string
    {
        return $this->read('passport.private_key', 'oauth-private.key');
    }

    public function publicKey(): string
    {
        return $this->read('passport.public_key', 'oauth-public.key');
    }

    /**
     * The public key as a JSON Web Key. The key id is the RFC 7638 thumbprint, so it changes exactly when the key does.
     *
     * @return array{kty: string, use: string, alg: string, kid: string, n: string, e: string}
     */
    public function jwk(): array
    {
        $resource = openssl_pkey_get_public($this->publicKey());
        $rsa = $resource ? (openssl_pkey_get_details($resource)['rsa'] ?? null) : null;

        if (! $rsa) {
            throw new RuntimeException('The Passport public key is not a readable RSA key.');
        }

        $n = $this->base64Url($rsa['n']);
        $e = $this->base64Url($rsa['e']);

        return [
            'kty' => 'RSA', 'use' => 'sig', 'alg' => 'RS256',
            'kid' => $this->base64Url(hash('sha256', json_encode(['e' => $e, 'kty' => 'RSA', 'n' => $n], JSON_THROW_ON_ERROR), true)),
            'n' => $n, 'e' => $e,
        ];
    }

    public function kid(): string
    {
        return $this->jwk()['kid'];
    }

    private function read(string $configKey, string $file): string
    {
        $configured = config($configKey);

        if (is_string($configured) && $configured !== '') {
            return str_replace('\n', "\n", $configured);
        }

        $path = Passport::keyPath($file);

        return is_readable($path) ? (string) file_get_contents($path) : throw new RuntimeException("Passport key $file is missing. Run: php artisan passport:keys");
    }

    private function base64Url(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }
}
