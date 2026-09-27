<?php

namespace App\Domain\Apps\Actions;

/**
 * Whether a URL is safe to actually send a request to, right now. DNS answers can change between when a
 * webhook URL is saved and when each delivery fires, so this is checked again immediately before every
 * send, not only when the URL is configured.
 */
class VerifyWebhookTarget
{
    private const LOOPBACK = ['localhost', '127.0.0.1', '::1'];

    public function __invoke(string $url): bool
    {
        $parts = parse_url($url);
        $scheme = is_array($parts) ? strtolower($parts['scheme'] ?? '') : '';
        $host = is_array($parts) ? strtolower(trim($parts['host'] ?? '', '[]')) : '';

        if ($host === '' || isset($parts['fragment']) || isset($parts['user']) || isset($parts['pass'])) {
            return false;
        }

        // Plain http only for local development, never for a real host.
        if (! ($scheme === 'https' || ($scheme === 'http' && in_array($host, self::LOOPBACK, true)))) {
            return false;
        }

        if (in_array($host, self::LOOPBACK, true)) {
            return true; // a deliberate local development target
        }

        $addresses = $this->resolve($host);

        return $addresses !== [] && collect($addresses)->every(
            fn (string $ip) => filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false
        );
    }

    /** @return list<string> every address this host currently resolves to */
    private function resolve(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return [$host];
        }

        $records = @dns_get_record($host, DNS_A | DNS_AAAA) ?: [];

        return array_values(array_unique(array_filter(array_map(fn (array $r) => $r['ip'] ?? $r['ipv6'] ?? null, $records))));
    }
}
