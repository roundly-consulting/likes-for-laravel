<?php

declare(strict_types=1);

namespace RoundlyConsulting\Likes\Tests\Support;

use Closure;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;

/**
 * Deterministic race simulation: runs a "concurrent request" right after the n-th read of
 * the likes table has executed, i.e. inside the window between a request's read and its
 * write. That is exactly where a double-click or a retry lands in production, so a guard
 * that only works sequentially fails here instead of in a host's logs.
 */
final class Interleave
{
    public static function afterRead(Closure $concurrent, int $nth = 1, string $table = 'likes'): void
    {
        $seen = 0;
        $fired = false;

        DB::listen(static function (QueryExecuted $query) use (&$seen, &$fired, $concurrent, $nth, $table): void {
            if ($fired || ! self::readsFrom($query->sql, $table)) {
                return;
            }

            if (++$seen < $nth) {
                return;
            }

            // Flip first: the concurrent request reads the same table and must not re-enter.
            $fired = true;
            $concurrent();
        });
    }

    private static function readsFrom(string $sql, string $table): bool
    {
        return preg_match('/^\s*select\b.*\bfrom\s+["`]?'.preg_quote($table, '/').'["`]?(\s|$)/is', $sql) === 1;
    }
}
