<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RoundlyConsulting\Likes\Models\Like;
use RoundlyConsulting\Likes\Tests\Models\ActorTestModel;
use RoundlyConsulting\Likes\Tests\Models\PostTestModel;

it('applies the forActor state', function (): void {
    $actor = ActorTestModel::create();

    $like = Like::factory()->forActor($actor)->create();

    expect($like->actor_id)->toBe($actor->getKey())
        ->and($like->actor_type)->toBe($actor->getMorphClass());
});

it('applies the forLikeable state', function (): void {
    $post = PostTestModel::create();

    $like = Like::factory()->forLikeable($post)->create();

    expect($like->likeable_id)->toBe($post->getKey())
        ->and($like->likeable_type)->toBe($post->getMorphClass());
});

it('applies the ofType state', function (): void {
    $like = Like::factory()->ofType('love')->create();

    expect($like->type)->toBe('love');
});

it('resolves morphs when built from actor and likeable states', function (): void {
    $actor = ActorTestModel::create();
    $post = PostTestModel::create();

    $like = Like::factory()->forActor($actor)->forLikeable($post)->create();

    expect($like->actor)->toBeInstanceOf(ActorTestModel::class)
        ->and($like->likeable)->toBeInstanceOf(PostTestModel::class);
});

/**
 * The factory wrote integer morph ids whatever `likes.key_type` built: Postgres refuses an
 * integer in a uuid column ("invalid input syntax for type uuid"), and SQLite's type affinity
 * hides it. The ids now match the configured key type.
 */
it('generates morph ids of the configured key type', function (string $keyType, Closure $valid): void {
    config()->set('likes.key_type', $keyType);

    $attributes = Like::factory()->raw();

    expect($valid($attributes['actor_id']))->toBeTrue()
        ->and($valid($attributes['likeable_id']))->toBeTrue();
})->with([
    'bigint' => ['bigint', fn (mixed $id): bool => is_int($id)],
    'uuid' => ['uuid', fn (mixed $id): bool => is_string($id) && Str::isUuid($id)],
    'ulid' => ['ulid', fn (mixed $id): bool => is_string($id) && Str::isUlid($id)],
]);

/**
 * The insert itself, into a table migrated with uuid / ulid morph ids. Green but weak on
 * sqlite and mysql; the pgsql leg is where an integer id is refused.
 */
it('creates a like in a uuid or ulid keyed table', function (string $keyType): void {
    config()->set('likes.key_type', $keyType);
    config()->set('likes.table', 'factory_likes');

    Schema::dropIfExists('factory_likes');
    $migration = require __DIR__.'/../../../database/migrations/create_likes_table.php';
    $migration->up();

    $like = Like::factory()->create();

    expect(Like::query()->whereKey($like->getKey())->exists())->toBeTrue();

    Schema::dropIfExists('factory_likes');
})->with(['uuid', 'ulid']);

it('defaults to the configured default reaction', function (): void {
    config()->set('likes.reactions', ['love', 'wow']);
    config()->set('likes.default_reaction', 'love');

    expect(Like::factory()->make()->type)->toBe('love');
});
