<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RoundlyConsulting\Likes\Tests\Models\ActorTestModel;
use RoundlyConsulting\Likes\Tests\Models\PostTestModel;
use RoundlyConsulting\Testing\Database\DriverMatrix;

/**
 * `trending.driver_expressions` was documented as "applied verbatim for that connection",
 * but orderByTrending() only ever built the portable expression — the override was read by
 * nothing on the query path. Every case keys the override by the live driver so it is
 * exercised on each leg of the matrix.
 */
beforeEach(function (): void {
    $this->post = PostTestModel::query()->create();

    ActorTestModel::query()->create()->like($this->post);
    ActorTestModel::query()->create()->like($this->post);
});

afterEach(function (): void {
    CarbonImmutable::setTestNow();
});

it('ranks with the override for the query connection driver', function (): void {
    config()->set('likes.trending.driver_expressions', [DriverMatrix::driver() => 'COUNT(*) * 1000']);

    $score = PostTestModel::query()->orderByTrending()->firstOrFail()->getAttribute('trending_score');

    expect((int) $score)->toBe(2000);
});

it('binds the window cut-off to every placeholder in the override', function (): void {
    config()->set('likes.trending.driver_expressions', [
        DriverMatrix::driver() => 'SUM(CASE WHEN created_at >= ? THEN 100 ELSE 1 END) + SUM(CASE WHEN created_at < ? THEN 10 ELSE 0 END)',
    ]);

    $recent = PostTestModel::query()->orderByTrending()->firstOrFail()->getAttribute('trending_score');

    CarbonImmutable::setTestNow(CarbonImmutable::now()->addMonth());
    $stale = PostTestModel::query()->orderByTrending()->firstOrFail()->getAttribute('trending_score');

    expect((int) $recent)->toBe(200)
        ->and((int) $stale)->toBe(22);
});

it('picks the override of the connection the query runs on, not the default one', function (): void {
    $live = DriverMatrix::driver();
    $other = $live === 'sqlite' ? 'pgsql' : 'sqlite';

    config()->set('likes.trending.driver_expressions', [$live => 'COUNT(*) * 1000', $other => 'COUNT(*) * 7']);

    // The default connection now names another driver; the query still runs on `testing`.
    config()->set('database.connections.likes_elsewhere', ['driver' => $other, 'database' => ':memory:']);
    config()->set('database.default', 'likes_elsewhere');

    try {
        $score = PostTestModel::on('testing')->orderByTrending()->firstOrFail()->getAttribute('trending_score');
    } finally {
        // The suite's teardown works on the default connection; hand it back.
        config()->set('database.default', 'testing');
    }

    expect((int) $score)->toBe(2000);
});

it('keeps the portable expression for a driver without an override', function (): void {
    config()->set('likes.trending.driver_expressions', ['no-such-driver' => 'COUNT(*) * 1000']);
    config()->set('likes.trending.recent_multiplier', 3);

    $score = PostTestModel::query()->orderByTrending()->firstOrFail()->getAttribute('trending_score');

    // 2 recent likes boosted ×3 plus 2 all-time.
    expect((float) $score)->toBe(8.0);
});

it('runs the documented Postgres decay-curve override', function (): void {
    // The example from the README and config/likes.php, verbatim.
    config()->set('likes.trending.driver_expressions', [
        'pgsql' => 'SUM(1 / POWER(EXTRACT(EPOCH FROM (NOW() - created_at)) / 3600 + 2, 1.8))',
    ]);

    // More likes, but three days old: a raw count ranks it first, the decay curve last.
    $older = PostTestModel::query()->create();
    ActorTestModel::query()->create()->like($older);
    ActorTestModel::query()->create()->like($older);
    ActorTestModel::query()->create()->like($older);
    DB::table('likes')->where('likeable_id', $older->getKey())->update(['created_at' => now()->subDays(3)]);

    $ids = PostTestModel::query()->orderByTrending()->pluck('id')->all();

    expect($ids)->toBe([$this->post->getKey(), $older->getKey()]);
})->skip(fn (): bool => DriverMatrix::driver() !== 'pgsql', 'a Postgres-only expression');
