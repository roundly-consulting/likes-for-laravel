<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use RoundlyConsulting\Likes\Models\Like;
use RoundlyConsulting\Likes\Tests\Models\ActorTestModel;
use RoundlyConsulting\Likes\Tests\Models\PostTestModel;

/**
 * The `likes.table` seam, exercised the way a host uses it: `LIKES_TABLE` set before
 * anything boots.
 *
 * The config file's own comment promises "Both the migration and the model read this
 * value, so changing it keeps the schema and queries in sync." Three readers existed —
 * the migration, `GivesLikes::likesOf()` and a `HasLikes` scope — and the **model was not
 * one of them**, so Eloquent derived `likes` from the class name while the migration built
 * `reactions`. Every like, unlike and relation read hit a table that did not exist.
 *
 * This is the media #27 family: a documented, shipped, env-wired config key that did not
 * do what it said. The reverse config contract cannot catch it (the key *is* read, just
 * not by everyone who must), which is why it needs a behavioural test.
 */
it('creates the renamed table rather than the default one', function (): void {
    expect(Schema::hasTable('reactions'))->toBeTrue()
        ->and(Schema::hasTable('likes'))->toBeFalse();
});

it('reads and writes likes through the renamed table', function (): void {
    $actor = ActorTestModel::query()->create();
    $post = PostTestModel::query()->create();

    expect($actor->like($post))->toBeTrue()
        ->and($actor->hasLiked($post))->toBeTrue()
        ->and($post->likesCount())->toBe(1)
        ->and(Like::query()->count())->toBe(1);
});

it('resolves the renamed table on the model itself', function (): void {
    expect((new Like)->getTable())->toBe('reactions');
});
