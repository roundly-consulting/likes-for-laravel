<?php

declare(strict_types=1);

use RoundlyConsulting\Likes\Exceptions\LikesException;
use RoundlyConsulting\Likes\LikeManager;
use RoundlyConsulting\Likes\Models\Like;
use RoundlyConsulting\Likes\PendingLike;
use RoundlyConsulting\Testing\Arch\ArchPresets;

/**
 * Likes shipped with no architecture test at all, so every preset here is a new guard
 * rather than a replacement.
 */
ArchPresets::strictTypes('RoundlyConsulting\Likes');

/**
 * Three deliberate extension points are exempt:
 *
 *  - Like, which `likes.model` invites a host to subclass (pinned by the preset below
 *    instead — the two rules pull in opposite directions on purpose);
 *  - LikesException, the base every likes error extends so a host can catch them
 *    uniformly;
 *  - LikeManager and PendingLike, which the package's own testing doubles extend
 *    (`LikesFake extends LikeManager`, `RecordingPendingLike extends PendingLike`) to
 *    implement `Likes::fake()`. Sealing either would break the package's testing seam.
 */
ArchPresets::finalByDefault('RoundlyConsulting\Likes', [
    Like::class,
    LikesException::class,
    LikeManager::class,
    PendingLike::class,
]);

/**
 * The counter-weight, and the fleet's 7×-shipped fatal: `final` on a config-swappable
 * model is a PHP fatal the moment a host uses the seam the config documents. The preset
 * also pins that `likes.model` really defaults to the packaged model, so the seam cannot
 * rot in the other direction either.
 */
ArchPresets::swappableModelsAreNotFinal([
    Like::class => 'likes.model',
]);

/**
 * Likes does no cryptography; the ban is a standing guard against a hand-rolled hash or id
 * scheme landing here rather than in crypto-for-laravel. Note the trending score is a
 * weighted SUM, not a digest — nothing here should ever need a primitive.
 */
ArchPresets::noLocalCryptoPrimitives('RoundlyConsulting\Likes');

/**
 * `likes.model` resolves through the LikeModel seam in Support. Adopted rather than
 * rejected as jwt/toolkit rejected it: likes has exactly the shape the preset targets — a
 * real Eloquent model behind a `*_model`-style key — so the stray-literal half has
 * something to say, and nothing here needs the late static binding the preset bans.
 */
ArchPresets::modelsResolveThroughSeam(__DIR__.'/../src', 'Support');

/**
 * The morph-key seam, guarded. Likes was the proven vector's other half: its actor and
 * likeable columns migrated off raw `$table->morphs()` onto `morphKey($name, KeyType::…)`
 * so a uuid/ulid host can flip its whole graph coherently — a hardcoded bigint id breaks
 * those hosts on Postgres, and SQLite type affinity hides it. This pin reds if a future
 * migration reintroduces a raw morph and bypasses the seam.
 */
ArchPresets::morphColumnsUseTheSeam(__DIR__.'/../database/migrations');

/**
 * The Dependency Policy as a test. No `alsoAllow`: likes' `require` ships only
 * php/illuminate/roundly, and the workflow installs test tooling with `--dev`, so nothing
 * legitimately lands in `require` that this must forgive. If it goes red the graph is
 * wrong — never widen the allow-list to quiet it.
 */
ArchPresets::runtimeRequireIsWhitelisted(__DIR__.'/../composer.json');

ArchPresets::noDebuggingLeftovers();
