<?php

declare(strict_types=1);

use RoundlyConsulting\Likes\Support\TrendingScore;

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

    expect($score['expression'])->toBe('SUM(CASE type WHEN ? THEN ? WHEN ? THEN ? ELSE ? END)')
        ->and($score['bindings'])->toBe(['like', 1, 'love', 4, 2]);
});

it('builds a portable trending expression bound to a window', function (): void {
    config()->set('likes.weights', []);
    config()->set('likes.trending.recent_multiplier', 5);

    $trending = TrendingScore::portableTrending();

    expect($trending['expression'])->toContain('created_at >= ?')
        ->and($trending['bindings'][1] ?? null)->toBe(5);
});

it('honours a per-driver override expression', function (): void {
    config()->set('likes.trending.driver_expressions', [
        'sqlite' => 'SUM(custom)',
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

    expect($trending['expression'])->toContain('CASE type WHEN ? THEN ?')
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
    config()->set('likes.trending.driver_expressions', ['sqlite' => 123]);

    expect(TrendingScore::trending()['expression'])->toContain('created_at >= ?');
});

it('falls back when the driver is not a string', function (): void {
    config()->set('likes.trending.driver_expressions', ['sqlite' => 'SUM(x)']);
    config()->set('database.connections.testing.driver', ['weird']);

    expect(TrendingScore::trending()['expression'])->toContain('created_at >= ?');
});
