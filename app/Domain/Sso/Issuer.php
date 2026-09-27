<?php

namespace App\Domain\Sso;

/** The base address apps know CAS by. It goes into ID tokens as `iss`, so it must not follow the request's host. */
final class Issuer
{
    public static function url(): string
    {
        return rtrim((string) config('cas.sso.issuer'), '/');
    }

    /** @return array<string, string> */
    public static function endpoints(): array
    {
        $base = self::url();

        return [
            'issuer' => $base,
            'discovery' => $base.'/.well-known/openid-configuration',
            'authorization' => $base.'/oauth/authorize',
            'token' => $base.'/oauth/token',
            'userinfo' => $base.'/oauth/userinfo',
            'jwks' => $base.'/oauth/jwks',
            'end_session' => $base.'/oauth/logout',
        ];
    }
}
