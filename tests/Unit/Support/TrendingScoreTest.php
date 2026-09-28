<?php

declare(strict_types=1);

use RoundlyConsulting\Likes\Support\TrendingScore;
use RoundlyConsulting\Likes\Tests\Models\CustomConnectionLike;
use RoundlyConsulting\Testing\Database\DriverMatrix;

it('builds a count expression with no weights', function (): void {
    config()->set('likes.weights', []);

    $score = TrendingScore::weightedSum();

    expect($score['expression'])->toBe('COUNT(*)')
        ->and($score['bindings'])->toBe([]);
});

it('builds a weighted case expression with bound values', function (): void {
    config()->set('likes.weights', ['like' => 1, 'love' => 4]);
    config()->set('likes.default_weight', 2);

    $score = TrendingScore::weightedSum();

    // The CASTs are load-bearing, not cosmetic: without them Postgres types the bound
    // weights as text and the enclosing SUM becomes `sum(text)`, which does not exist.
    // The bindings are unchanged — every value is still bound, never inlined.
    expect($score['expression'])->toBe(
        'SUM(CASE type WHEN ? THEN CAST(? AS DECIMAL(20,10)) WHEN ? THEN CAST(? AS DECIMAL(20,10))'
        .' ELSE CAST(? AS DECIMAL(20,10)) END)',
    )
        ->and($score['bindings'])->toBe(['like', 1, 'love', 4, 2]);
});

it('builds a portable trending expression bound to a window', function (): void {
    config()->set('likes.weights', []);
    config()->set('likes.trending.recent_multiplier', 5);

    $trending = TrendingScore::portableTrending();

    expect($trending['expression'])->toContain('created_at >= ?')
        ->and($trending['bindings'][1] ?? null)->toBe(5);
});

/**
 * Keyed by the driver the suite is actually running on, not a hard-coded 'sqlite'. The
 * literal made this case exercise the override branch on the sqlite leg and silently take
 * the *fallback* branch on any other — green either way, proving nothing on the leg that
 * matters. This is the toolkit row's lesson: a test-side sqlite assumption is invisible
 * until a real engine runs the suite.
 */
it('honours a per-driver override expression', function (): void {
    config()->set('likes.trending.driver_expressions', [
        DriverMatrix::driver() => 'SUM(custom)',
    ]);

    $trending = TrendingScore::trending();

    expect($trending['expression'])->toBe('SUM(custom)');
});

it('falls back to the portable expression without an override', function (): void {
    config()->set('likes.trending.driver_expressions', []);

    $trending = TrendingScore::trending();

    expect($trending['expression'])->toContain('created_at >= ?');
});

it('builds a weighted portable trending expression', function (): void {
    config()->set('likes.weights', ['love' => 4]);
    config()->set('likes.default_weight', 1);

    $trending = TrendingScore::portableTrending();

    expect($trending['expression'])->toContain('CASE type WHEN ? THEN CAST(? AS DECIMAL(20,10))')
        ->and($trending['bindings'])->toContain('love');
});

it('ignores non-numeric and non-string weight entries', function (): void {
    config()->set('likes.weights', ['love' => 'heavy', 7 => 3]);

    $score = TrendingScore::weightedSum();

    expect($score['expression'])->toBe('COUNT(*)');
});

it('falls back when config values have the wrong type', function (): void {
    config()->set('likes.weights', 'not-an-array');
    config()->set('likes.default_weight', 'x');
    config()->set('likes.trending.recent_multiplier', 'y');
    config()->set('likes.trending.window', 123);
    config()->set('likes.trending.driver_expressions', 'nope');

    $score = TrendingScore::weightedSum();
    $trending = TrendingScore::trending();

    expect($score['expression'])->toBe('COUNT(*)')
        ->and($trending['bindings'][1] ?? null)->toBe(3)
        ->and(TrendingScore::since())->toBeString();
});

it('ignores a non-string driver override', function (): void {
    // Keyed by the live driver so the non-string branch is really reached on every leg.
    config()->set('likes.trending.driver_expressions', [DriverMatrix::driver() => 123]);

    expect(TrendingScore::trending()['expression'])->toContain('created_at >= ?');
});

it('reads the driver from the likes model connection, not the default connection', function (): void {
    $live = DriverMatrix::driver();
    $other = $live === 'sqlite' ? 'pgsql' : 'sqlite';

    config()->set('likes.trending.driver_expressions', [$live => 'SUM(live)', $other => 'SUM(other)']);
    config()->set('likes.model', CustomConnectionLike::class);
    config()->set('database.connections.likes_elsewhere', ['driver' => $other, 'database' => ':memory:']);
    config()->set('database.default', 'likes_elsewhere');

    try {
        $expression = TrendingScore::trending()['expression'];
    } finally {
        // The suite's teardown works on the default connection; hand it back.
        config()->set('database.default', 'testing');
    }

    expect($expression)->toBe('SUM(live)');
});

it('takes an explicit driver', function (): void {
    config()->set('likes.trending.driver_expressions', ['pgsql' => 'SUM(pg) + ?', 'mysql' => 'SUM(my)']);

    $trending = TrendingScore::trending('pgsql');

    expect($trending['expression'])->toBe('SUM(pg) + ?')
        ->and($trending['bindings'])->toBe([TrendingScore::since()])
        ->and(TrendingScore::trending('mysql')['bindings'])->toBe([])
        ->and(TrendingScore::trending('sqlsrv')['expression'])->toContain('created_at >= ?');
});

it('ignores an empty override', function (): void {
    config()->set('likes.trending.driver_expressions', ['pgsql' => '']);

    expect(TrendingScore::trending('pgsql')['expression'])->toContain('created_at >= ?');
});
