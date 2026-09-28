<?php

declare(strict_types=1);

namespace RoundlyConsulting\Likes\Support;

use Carbon\CarbonImmutable;

/**
 * Builds the portable SQL expressions behind the ranking scopes: a weighted
 * reaction-sum for orderByLikeScore() and a recency-weighted hybrid for
 * orderByTrending(). Everything here is ANSI-portable (SUM, CASE, a bound
 * timestamp comparison) so it runs on SQLite, MySQL and Postgres alike.
 *
 * All config-derived values are passed as bound parameters, never inlined, so
 * the generated SQL templates stay literal and injection-safe. That is what makes
 * the explicit CASTs necessary: a bound parameter carries no type, and Postgres
 * will not guess one inside an aggregate. Every numeric binding that reaches an
 * arithmetic or aggregate context is therefore cast at the call site — see
 * {@see self::weightedCase()}. Until this row that was missing, and the portability
 * claim above was simply untrue on Postgres for any host that configured
 * `likes.weights` or a fractional `trending.recent_multiplier`.
 */
final class TrendingScore
{
    /**
     * Build a weighted-sum expression and its bindings. With the default all-1
     * weights this is equivalent to COUNT(*).
     *
     * @return array{expression: literal-string, bindings: list<string|int|float>}
     */
    public static function weightedSum(): array
    {
        $weights = self::weights();

        if ($weights === []) {
            return ['expression' => 'COUNT(*)', 'bindings' => []];
        }

        [$case, $bindings] = self::weightedCase($weights);

        return ['expression' => 'SUM('.$case.')', 'bindings' => $bindings];
    }

    /**
     * Build the portable trending expression and bindings used by the ranking
     * scope: a weighted all-time score plus a boosted weighted count of likes
     * within the recency window. The window cut-off is bound as a parameter so
     * no DB datetime function is needed. Always a literal SQL template.
     *
     * @return array{expression: literal-string, bindings: list<string|int|float>}
     */
    public static function portableTrending(): array
    {
        $weights = self::weights();
        $multiplier = self::recentMultiplier();

        if ($weights === []) {
            // recent count + all-time count, boosted. The multiplier is cast for the same
            // reason the weights are: `SUM(...) * ?` leaves Postgres to type the parameter
            // from its neighbour, so it parses the value as bigint and a documented
            // fractional multiplier dies with "invalid input syntax for type bigint".
            $expression = '('
                .'SUM(CASE WHEN created_at >= ? THEN 1 ELSE 0 END) * CAST(? AS DECIMAL(20,10))'
                .' + COUNT(*))';

            return ['expression' => $expression, 'bindings' => [self::since(), $multiplier]];
        }

        [$case, $caseBindings] = self::weightedCase($weights);

        // recent_weighted * multiplier + all_time_weighted
        $expression = '('
            .'SUM(CASE WHEN created_at >= ? THEN ('.$case.') ELSE 0 END) * CAST(? AS DECIMAL(20,10))'
            .' + SUM('.$case.'))';

        $bindings = [self::since(), ...$caseBindings, $multiplier, ...$caseBindings];

        return ['expression' => $expression, 'bindings' => $bindings];
    }

    /**
     * Resolve the trending expression for a database driver: the host's raw override from
     * `likes.trending.driver_expressions` when one is configured for that driver, otherwise
     * the portable template. Every `?` in an override is bound to the window cut-off
     * ({@see self::since()}), so an override can reference the window as often as it needs.
     *
     * The driver defaults to that of the likes model's connection. orderByTrending() passes
     * the driver of the query it builds, since that is where the SQL runs. The override is
     * host-supplied raw SQL, so the return type widens to a plain string.
     *
     * @return array{expression: string, bindings: list<string|int|float>}
     */
    public static function trending(?string $driver = null): array
    {
        $override = self::driverOverride($driver ?? self::likesDriver());

        if ($override !== null) {
            $since = self::since();
            $bindings = [];

            for ($i = substr_count($override, '?'); $i > 0; $i--) {
                $bindings[] = $since;
            }

            return ['expression' => $override, 'bindings' => $bindings];
        }

        return self::portableTrending();
    }

    public static function since(): string
    {
        $window = config('likes.trending.window', '7 days');
        $window = is_string($window) && $window !== '' ? $window : '7 days';

        return CarbonImmutable::now()
            ->sub($window)
            ->toDateTimeString();
    }

    /**
     * Build a `CASE type WHEN ? THEN CAST(? AS DECIMAL(20,10)) … END` template plus the
     * bindings (type, weight) pairs followed by the default weight. The template is a
     * literal string; all values are bound.
     *
     * The CASTs are not decoration — without them this whole feature throws on Postgres.
     * A bare `?` has no type, so Postgres infers `text` for the THEN/ELSE branches, the
     * CASE resolves to text, and the enclosing aggregate becomes `sum(text)`:
     *
     *     SQLSTATE[42883]: Undefined function: 7 ERROR: function sum(text) does not exist
     *
     * SQLite is dynamically typed and never noticed, which is how `likes.weights` — a
     * documented, tested feature — shipped broken on every real Postgres install.
     *
     * `DECIMAL(20,10)` rather than `NUMERIC`: DECIMAL(M,D) is the one spelling in the CAST
     * grammar of all three engines this class claims to support (Postgres treats it as
     * NUMERIC, MySQL lists DECIMAL explicitly and does *not* accept NUMERIC, SQLite gives
     * it NUMERIC affinity). Bare `DECIMAL` is unusable: MySQL defaults it to (10,0) and
     * would silently truncate fractional weights to integers.
     *
     * @param  array<string, int|float>  $weights
     * @return array{0: literal-string, 1: list<string|int|float>}
     */
    private static function weightedCase(array $weights): array
    {
        $case = 'CASE type';
        $bindings = [];

        foreach ($weights as $type => $weight) {
            $case .= ' WHEN ? THEN CAST(? AS DECIMAL(20,10))';
            $bindings[] = $type;
            $bindings[] = $weight;
        }

        $case .= ' ELSE CAST(? AS DECIMAL(20,10)) END';
        $bindings[] = self::defaultWeight();

        return [$case, $bindings];
    }

    /**
     * @return array<string, int|float>
     */
    private static function weights(): array
    {
        $weights = config('likes.weights', []);

        if (! is_array($weights)) {
            return [];
        }

        $resolved = [];

        foreach ($weights as $type => $weight) {
            if (is_string($type) && (is_int($weight) || is_float($weight))) {
                $resolved[$type] = $weight;
            }
        }

        return $resolved;
    }

    private static function defaultWeight(): int|float
    {
        $default = config('likes.default_weight', 1);

        return is_int($default) || is_float($default) ? $default : 1;
    }

    private static function recentMultiplier(): int|float
    {
        $multiplier = config('likes.trending.recent_multiplier', 3);

        return is_int($multiplier) || is_float($multiplier) ? $multiplier : 3;
    }

    private static function driverOverride(string $driver): ?string
    {
        $expressions = config('likes.trending.driver_expressions', []);

        if (! is_array($expressions)) {
            return null;
        }

        $override = $expressions[$driver] ?? null;

        return is_string($override) && trim($override) !== '' ? $override : null;
    }

    private static function likesDriver(): string
    {
        $model = LikeModel::class();

        return (new $model)->getConnection()->getDriverName();
    }
}
