<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use RoundlyConsulting\Likes\LikesServiceProvider;
use RoundlyConsulting\Likes\Tests\Models\ActorTestModel;
use RoundlyConsulting\Likes\Tests\Models\PostTestModel;
use RoundlyConsulting\Testing\Database\DriverMatrix;

/**
 * Likes ships exactly one CREATE and zero foreign keys — both ends of a like are
 * unconstrained morphs (`actor`, `likeable`), deliberately, because a host's actor and
 * likeable can live in any table. Measured, not taken from the row spec.
 *
 * That shape decides what is worth pinning, and it is worth being explicit about why:
 *
 *  - **M (`toHaveRunnableMigrationOrder`) is not adopted.** With one migration and no FK
 *    edges there is no order to get wrong.
 *  - **The R negative control (`toRejectBrokenOrderOnConnection`) is not adoptable.** It
 *    asserts the engine *refuses* a reordered set — but reversing a one-file list is the
 *    same list, and with no foreign keys Postgres has nothing to refuse. It would fail
 *    loudly by design, which is the assertion working correctly against a shape it does
 *    not fit, not a red to chase and not a package defect.
 */
/**
 * P — the publish-only guards. The fleet publishes migrations timestamped rather than
 * auto-loading them; doing both runs both copies and dies on a duplicate table (bug #5, on
 * three packages). `count: 1` pins the file count so neither check can pass over an empty
 * or relocated directory.
 */
it('never auto-loads its migration — the host publishes it', function (): void {
    expect(LikesServiceProvider::class)->toNotAutoLoadMigrations();
});

it('publishes its migration timestamp-injected into the host', function (): void {
    expect(LikesServiceProvider::class)->toPublishMigrationsTimestamped('likes-migrations', 1);
});

/**
 * R (`toApplyOnConnection`) is **deliberately not adopted yet** — HELD pending a fix in
 * testing-for-laravel, not because it does not fit.
 *
 * On the pgsql leg `DriverMatrix::configure()` builds `connections.testing` and
 * `connections.pgsql` from the same `connectionConfig('pgsql')` — identical host, port and
 * database. They are one physical database reached through two PDO sessions.
 * `MigrationRunner::runFiles()` drops every table on entry and again in `finally`, so
 * `toApplyOnConnection` pulls the schema out from under the *live suite* mid-run.
 *
 * This row saw it directly and misread it as leftover local state: four consecutive pgsql
 * runs of this suite gave 7 failed, 1 failed, 201 passed, 201 passed, with the failures
 * landing on unrelated tests each time. `executionOrder="random"` makes it seed-dependent,
 * so a green run currently proves nothing.
 *
 * The rest of the real-engine value is kept: the whole suite still runs on Postgres on the
 * leg, and the driver-truth assertion below makes a lying leg impossible. Re-add R here
 * once the connection-isolation fix lands.
 */

/**
 * TrendingScore's docblock claims its SQL is "ANSI-portable (SUM, CASE, a bound timestamp
 * comparison) so it runs on SQLite, MySQL and Postgres alike". That claim had never been
 * tested against anything but SQLite. A weighted SUM(CASE …) inside an ORDER BY, with a
 * GROUP BY beside it, is exactly where Postgres is stricter than SQLite — so the claim is
 * worth executing rather than believing.
 */
it('runs the ranking and summary SQL on the configured engine', function (): void {
    $actor = ActorTestModel::query()->create();
    $post = PostTestModel::query()->create();

    config()->set('likes.reactions', ['like', 'love']);
    config()->set('likes.weights', ['like' => 1, 'love' => 3]);

    $actor->react($post, 'love');

    $ranked = PostTestModel::query()->orderByTrending()->get();
    $scored = PostTestModel::query()->orderByLikeScore()->get();

    expect($ranked)->toHaveCount(1)
        ->and($scored)->toHaveCount(1)
        // The grouped per-type aggregate — the other piece of raw SQL likes ships.
        ->and($post->reactionSummary()->total)->toBe(1)
        // The driver actually under test, so a leg that quietly stayed on sqlite is
        // visible in the failure rather than passing as a "postgres" run. This fires
        // automatically; step 8's skip-count check is the human backstop.
        ->and(DB::connection()->getDriverName())->toBe(DriverMatrix::driver());
});
