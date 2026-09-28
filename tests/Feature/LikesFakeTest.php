<?php

declare(strict_types=1);

use PHPUnit\Framework\AssertionFailedError;
use RoundlyConsulting\Likes\Exceptions\InvalidReactionTypeException;
use RoundlyConsulting\Likes\Exceptions\NoAuthenticatedActorException;
use RoundlyConsulting\Likes\Facades\Likes;
use RoundlyConsulting\Likes\LikeManager;
use RoundlyConsulting\Likes\Models\Like;
use RoundlyConsulting\Likes\Testing\LikesFake;
use RoundlyConsulting\Likes\Tests\Models\ActorTestModel;
use RoundlyConsulting\Likes\Tests\Models\CurrentActorResolver;
use RoundlyConsulting\Likes\Tests\Models\PostTestModel;

beforeEach(function (): void {
    config()->set('likes.reactions', ['like', 'love']);

    $this->actor = ActorTestModel::query()->create();
    $this->post = PostTestModel::query()->create();
    $this->other = PostTestModel::query()->create();
});

it('swaps the manager and records likes while still performing them', function (): void {
    $fake = Likes::fake();

    expect($fake)->toBeInstanceOf(LikesFake::class)
        ->and(app(LikeManager::class))->toBe($fake);

    Likes::actor($this->actor)->like($this->post);

    // Still performed against the database.
    expect(Like::query()->whereNull('deleted_at')->count())->toBe(1);

    $fake->assertLiked($this->post);
});

it('drives assertLiked and assertNotLiked', function (): void {
    $fake = Likes::fake();

    Likes::actor($this->actor)->like($this->post);

    $fake->assertLiked($this->post);
    $fake->assertNotLiked($this->other);
});

it('is actor and type aware in assertLikedBy', function (): void {
    $fake = Likes::fake();

    Likes::actor($this->actor)->as('love')->like($this->post);

    $fake->assertLikedBy($this->actor, $this->post, 'love');
});

it('passes assertNothingLiked when nothing recorded and fails otherwise', function (): void {
    $fake = Likes::fake();

    $fake->assertNothingLiked();

    Likes::actor($this->actor)->like($this->post);

    expect(fn () => $fake->assertNothingLiked())->toThrow(AssertionFailedError::class);
});

it('counts likes via assertLikedCount and assertLikedTimes', function (): void {
    $fake = Likes::fake();

    Likes::actor($this->actor)->like($this->post);
    Likes::actor($this->actor)->like($this->other);

    $fake->assertLikedCount(2);
    $fake->assertLikedTimes($this->post, 1);
});

it('records a toggle as a like or an unlike', function (): void {
    $fake = Likes::fake();

    Likes::actor($this->actor)->toggle($this->post);
    $fake->assertLiked($this->post);

    Likes::actor($this->actor)->toggle($this->post);
    $fake->assertLikedTimes($this->post, 1);
});

it('records a react as a reaction, not a like', function (): void {
    $fake = Likes::fake();

    Likes::actor($this->actor)->as('love')->react($this->post);

    $fake->assertReacted($this->post, 'love');
    $fake->assertNothingLiked();
});

it('records an unlike without marking the model liked', function (): void {
    $fake = Likes::fake();

    Likes::actor($this->actor)->like($this->post);
    Likes::actor($this->actor)->unlike($this->post);

    // The unlike record is skipped by the like-only assertion.
    $fake->assertLikedTimes($this->post, 1);
    expect(Like::query()->whereNull('deleted_at')->count())->toBe(0);
});

it('records manager-level like, unlike, toggle and react', function (): void {
    config()->set('likes.actor_resolver', CurrentActorResolver::class);

    $fake = Likes::fake();

    Likes::like($this->post);
    $fake->assertLiked($this->post);

    Likes::unlike($this->post);
    Likes::toggle($this->post);
    Likes::react($this->other);

    $fake->assertUnliked($this->post);
    $fake->assertLikedTimes($this->post, 2);
    $fake->assertReacted($this->other);
});

it('skips unlike records when asserting a like', function (): void {
    $fake = Likes::fake();

    Likes::actor($this->actor)->like($this->post);
    Likes::actor($this->actor)->unlike($this->post);

    // assertNotLiked must skip the unlike record (operation mismatch) and still
    // see the like, so it fails — proving unlikes are not counted as likes.
    expect(fn () => $fake->assertNotLiked($this->post))
        ->toThrow(AssertionFailedError::class);
});

it('does not match assertLikedBy for a different actor', function (): void {
    $fake = Likes::fake();
    $other = ActorTestModel::query()->create();

    Likes::actor($this->actor)->like($this->post);

    expect(fn () => $fake->assertLikedBy($other, $this->post))
        ->toThrow(AssertionFailedError::class);
});

/**
 * The fake recorded a write before performing it, so a refused write still satisfied the
 * assertions: `like($post, 'nope')` threw, yet `assertLikedBy()` passed over zero rows.
 */
it('does not record a write the package refused', function (Closure $write): void {
    $fake = Likes::fake();

    expect(fn () => $write($this->actor, $this->post))->toThrow(InvalidReactionTypeException::class);

    $fake->assertNothingLiked();
    $fake->assertNothingUnliked();
    $fake->assertNothingReacted();
})->with([
    'like' => [fn ($actor, $post) => $actor->like($post, 'nope')],
    'unlike' => [fn ($actor, $post) => $actor->unlike($post, 'nope')],
    'react' => [fn ($actor, $post) => $actor->react($post, 'nope')],
    'toggle' => [fn ($actor, $post) => $actor->toggleLike($post, 'nope')],
    'likeMany' => [fn ($actor, $post) => $actor->likeMany([$post], 'nope')],
    'unlikeMany' => [fn ($actor, $post) => $actor->unlikeMany([$post], 'nope')],
]);

it('does not record a write without a resolvable actor', function (): void {
    $fake = Likes::fake();

    expect(fn () => Likes::like($this->post))->toThrow(NoAuthenticatedActorException::class);

    $fake->assertNothingLiked();
});

it('records a completed bulk write once per model', function (): void {
    $fake = Likes::fake();

    $this->actor->likeMany([$this->post, $this->other]);

    $fake->assertLikedCount(2);
    $fake->assertLikedBy($this->actor, $this->other);
});
