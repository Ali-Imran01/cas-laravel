<?php

namespace App\Domain\Audit;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Audit\Models\LoginAttempt;
use App\Domain\Identity\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Filtering shared by the on-screen lists and the CSV export, so both always show the same rows. */
final class AuditQueries
{
    /**
     * @param  array<string, string>  $f  search, action, actor, subject, result, from, to
     * @return Builder<AuditLog>
     */
    public static function log(array $f): Builder
    {
        $query = AuditLog::query()->with('actor')->latest('id');

        if (! empty($f['search'])) {
            $query->whereRaw('lower(description) like ?', ['%'.self::like($f['search']).'%']);
        }
        if (! empty($f['action'])) {
            $query->where('action', $f['action']);
        }
        if (! empty($f['actor'])) {
            $term = '%'.self::like($f['actor']).'%';
            $query->whereIn('actor_id', User::withTrashed()->where(fn ($q) => $q
                ->whereRaw('lower(name) like ?', [$term])->orWhereRaw('lower(staff_id) like ?', [$term]))->select('id'));
        }
        if (! empty($f['subject'])) {
            // The filter offers short names; rows store the full class name.
            $query->where('auditable_type', 'like', '%\\'.addcslashes($f['subject'], '%_\\'));
        }
        if (! empty($f['result'])) {
            $query->where('result', $f['result']);
        }

        return self::between($query, $f);
    }

    /**
     * @param  array<string, string>  $f  search, result, method, from, to
     * @return Builder<LoginAttempt>
     */
    public static function signIns(array $f): Builder
    {
        $query = LoginAttempt::query()->latest('id');

        if (! empty($f['search'])) {
            $query->whereRaw('lower(identifier) like ?', ['%'.self::like($f['search']).'%']);
        }
        if (! empty($f['result'])) {
            $query->where('result', $f['result']);
        }
        if (! empty($f['method'])) {
            $query->where('method', $f['method']);
        }

        return self::between($query, $f);
    }

    /**
     * @template T of Model
     *
     * @param  Builder<T>  $query
     * @param  array<string, string>  $f
     * @return Builder<T>
     */
    private static function between(Builder $query, array $f): Builder
    {
        if (! empty($f['from'])) {
            $query->where('created_at', '>=', $f['from'].' 00:00:00');
        }
        if (! empty($f['to'])) {
            $query->where('created_at', '<', date('Y-m-d', strtotime($f['to'].' +1 day')).' 00:00:00');
        }

        return $query;
    }

    /** Lower-cased, with LIKE wildcards in the user's text made literal. */
    private static function like(string $term): string
    {
        return addcslashes(mb_strtolower(trim($term)), '\\%_');
    }
}
