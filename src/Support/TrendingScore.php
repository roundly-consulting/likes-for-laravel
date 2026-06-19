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
 * the generated SQL templates stay literal and injection-safe.
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
            // recent count + all-time count, boosted.
            $expression = '('
                .'SUM(CASE WHEN created_at >= ? THEN 1 ELSE 0 END) * ?'
                .' + COUNT(*))';

            return ['expression' => $expression, 'bindings' => [self::since(), $multiplier]];
        }

        [$case, $caseBindings] = self::weightedCase($weights);

        // recent_weighted * multiplier + all_time_weighted
        $expression = '('
            .'SUM(CASE WHEN created_at >= ? THEN ('.$case.') ELSE 0 END) * ?'
            .' + SUM('.$case.'))';

        $bindings = [self::since(), ...$caseBindings, $multiplier, ...$caseBindings];

        return ['expression' => $expression, 'bindings' => $bindings];
    }

    /**
     * Resolve the trending expression, honouring a per-driver raw override when
     * configured for the active connection, otherwise the portable template.
     * The override is host-supplied raw SQL, so the return type widens to a
     * plain string; advanced hosts that build their own queries use this.
     *
     * @return array{expression: string, bindings: list<string|int|float>}
     */
    public static function trending(): array
    {
        $override = self::driverOverride();

        if ($override !== null) {
            return ['expression' => $override, 'bindings' => [self::since()]];
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
     * Build a `CASE type WHEN ? THEN ? … ELSE ? END` template plus the bindings
     * (type, weight) pairs followed by the default weight. The template is a
     * literal string; all values are bound.
     *
     * @param  array<string, int|float>  $weights
     * @return array{0: literal-string, 1: list<string|int|float>}
     */
    private static function weightedCase(array $weights): array
    {
        $case = 'CASE type';
        $bindings = [];

        foreach ($weights as $type => $weight) {
            $case .= ' WHEN ? THEN ?';
            $bindings[] = $type;
            $bindings[] = $weight;
        }

        $case .= ' ELSE ? END';
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

    private static function driverOverride(): ?string
    {
        $expressions = config('likes.trending.driver_expressions', []);

        if (! is_array($expressions)) {
            return null;
        }

        $connection = config('database.default');

        if (! is_string($connection)) {
            return null;
        }

        $driver = config("database.connections.{$connection}.driver");

        if (! is_string($driver)) {
            return null;
        }

        $override = $expressions[$driver] ?? null;

        return is_string($override) ? $override : null;
    }
}
