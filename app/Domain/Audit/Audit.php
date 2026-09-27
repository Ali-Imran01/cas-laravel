<?php

namespace App\Domain\Audit;

use App\Domain\Audit\Enums\AuthResult;
use App\Domain\Audit\Enums\LoginMethod;
use App\Domain\Audit\Models\AuditLog;
use App\Domain\Audit\Models\LoginAttempt;
use App\Domain\Identity\Models\User;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Single entry point for the audit trail. Actions call `Audit::record()` next to the change they make,
 * so the log cannot drift from what the code really does. Secrets are redacted here, once.
 */
final class Audit
{
    /** Any key containing one of these has its value replaced, in both old and new values. */
    private const SENSITIVE = ['password', 'secret', 'token', 'recovery'];

    /**
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $new
     */
    public static function record(string $action, string $description, ?Model $subject = null, array $old = [], array $new = [], AuthResult $result = AuthResult::Success, ?int $actorId = null): AuditLog
    {
        return AuditLog::create([
            'actor_id' => $actorId ?? app(AuditContext::class)->actorId ?? Auth::id(),
            'action' => $action,
            'auditable_type' => $subject?->getMorphClass(),
            'auditable_id' => $subject?->getKey(),
            'description' => Str::limit($description, 255, ''),
            'old_values' => $old === [] ? null : self::redact($old),
            'new_values' => $new === [] ? null : self::redact($new),
            'result' => $result,
            'ip_address' => request()->ip(),
            'user_agent' => self::agent(),
        ]);
    }

    public static function loginAttempt(?User $user, string $identifier, LoginMethod $method, AuthResult $result, ?string $reason = null, ?int $applicationId = null): LoginAttempt
    {
        return LoginAttempt::create([
            'user_id' => $user?->id,
            'application_id' => $applicationId,
            'identifier' => Str::limit(trim($identifier), 191, ''),
            'method' => $method,
            'result' => $result,
            'failure_reason' => $reason,
            'ip_address' => request()->ip(),
            'user_agent' => self::agent(),
        ]);
    }

    /**
     * The keys that differ between two states, as [old, new]; unchanged keys are dropped.
     *
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    public static function diff(array $before, array $after): array
    {
        $old = $new = [];

        foreach ($after as $key => $value) {
            if (($before[$key] ?? null) !== $value) {
                $old[$key] = $before[$key] ?? null;
                $new[$key] = $value;
            }
        }

        return [$old, $new];
    }

    /** Runs $callback with someone other than the signed-in user as the actor (queued jobs, console). */
    public static function asActor(?int $actorId, Closure $callback): mixed
    {
        $context = app(AuditContext::class);
        $previous = $context->actorId;
        $context->actorId = $actorId;

        try {
            return $callback();
        } finally {
            $context->actorId = $previous;
        }
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private static function redact(array $values): array
    {
        $out = [];

        foreach ($values as $key => $value) {
            $sensitive = Str::contains(strtolower($key), self::SENSITIVE);
            $out[$key] = match (true) {
                $sensitive => '[redacted]',
                is_array($value) => self::redact($value),
                default => $value,
            };
        }

        return $out;
    }

    private static function agent(): ?string
    {
        $agent = request()->userAgent();

        return $agent === null ? null : Str::limit($agent, 255, '');
    }
}
