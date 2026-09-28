<?php

declare(strict_types=1);

use PHPUnit\Framework\AssertionFailedError;
use RoundlyConsulting\Likes\Facades\Likes;
use RoundlyConsulting\Likes\LikeManager;
use RoundlyConsulting\Likes\Models\Like;
use RoundlyConsulting\Likes\Testing\InteractsWithLikes;
use RoundlyConsulting\Likes\Testing\LikesFake;
use RoundlyConsulting\Likes\Tests\Models\ActorTestModel;
use RoundlyConsulting\Likes\Tests\Models\PostTestModel;

uses(InteractsWithLikes::class);

beforeEach(function (): void {
    config()->set('likes.reactions', ['like', 'love']);

    $this->actor = ActorTestModel::query()->create();
    $this->post = PostTestModel::query()->create();
    $this->other = PostTestModel::query()->create();
});

it('is a real static on the facade that also takes over dependency injection', function (): void {
    $fake = Likes::fake();

    expect($fake)->toBeInstanceOf(LikesFake::class)
        ->and(Likes::getFacadeRoot())->toBe($fake)
        ->and(app(LikeManager::class))->toBe($fake);
});

it('records likes made through the GivesLikes trait', function (): void {
    $fake = Likes::fake();

    $this->actor->like($this->post, 'love');

    $fake->assertLikedBy($this->actor, $this->post, 'love');
    expect(Like::query()->count())->toBe(1);
});

it('records unlikes, toggles and reactions made through the GivesLikes trait', function (): void {
    $fake = Likes::fake();

    $this->actor->toggleLike($this->post);
    $this->actor->unlike($this->post);
    $this->actor->react($this->other, 'love');
    $this->actor->switchReaction($this->other, 'like');

    $fake->assertLikedTimes($this->post, 1);
    $fake->assertUnliked($this->post, by: $this->actor);
    $fake->assertReacted($this->other, 'love', $this->actor);
    $fake->assertReacted($this->other, 'like');
});

it('records bulk likes and unlikes per model, from the facade and the trait', function (): void {
    $fake = Likes::fake();

    Likes::actor($this->actor)->likeMany([$this->post, $this->other]);
    $fake->assertLikedCount(2);

    $this->actor->unlikeMany([$this->post, $this->other]);
    $fake->assertUnliked($this->post);
    $fake->assertUnliked($this->other);

    expect(Like::query()->count())->toBe(0);
});

it('records flat bulk calls as the resolved actor, consuming a generator once', function (): void {
    $this->actingAs($this->actor);
    $fake = Likes::fake();

    $models = (function () {
        yield $this->post;
        yield $this->other;
    })();

    Likes::likeMany($models);

    $fake->assertLikedBy($this->actor, $this->post);
    $fake->assertLikedBy($this->actor, $this->other);
    expect(Like::query()->count())->toBe(2);

    Likes::unlikeMany([$this->post]);

    $fake->assertUnliked($this->post, by: $this->actor);
    expect(Like::query()->count())->toBe(1);
});

it('answers has() and for() from the state the fake still writes', function (): void {
    $this->actingAs($this->actor);
    $fake = Likes::fake();

    Likes::like($this->post);

    expect(Likes::has($this->post))->toBeTrue()
        ->and(Likes::actor($this->actor)->has($this->other))->toBeFalse()
        ->and($this->actor->hasLiked($this->post))->toBeTrue()
        ->and(Likes::for($this->post)->count())->toBe(1);

    $fake->assertLiked($this->post);
});

it('records the InteractsWithLikes helpers', function (): void {
    $fake = Likes::fake();

    $this->likeAs($this->post, 'love', $this->actor);
    $this->unlikeAs($this->post, 'love', $this->actor);
    $this->toggleAs($this->other, null, $this->actor);

    $fake->assertLikedBy($this->actor, $this->post, 'love');
    $fake->assertUnliked($this->post, 'love');
    $fake->assertLiked($this->other);
});

it('matches an untyped write against the default reaction type', function (): void {
    $fake = Likes::fake();

    Likes::actor($this->actor)->like($this->post);

    $fake->assertLiked($this->post, 'like');
    $fake->assertLikedBy($this->actor, $this->post, 'like');
    expect(fn () => $fake->assertLiked($this->post, 'love'))->toThrow(AssertionFailedError::class);
});

it('fails assertUnliked and assertNothingUnliked correctly', function (): void {
    $fake = Likes::fake();

    $fake->assertNothingUnliked();
    expect(fn () => $fake->assertUnliked($this->post))->toThrow(AssertionFailedError::class);

    $this->actor->unlike($this->post);

    $fake->assertUnliked($this->post);
    expect(fn () => $fake->assertNothingUnliked())->toThrow(AssertionFailedError::class)
        ->and(fn () => $fake->assertUnliked($this->post, by: ActorTestModel::query()->create()))->toThrow(AssertionFailedError::class)
        ->and(fn () => $fake->assertUnliked($this->other))->toThrow(AssertionFailedError::class);
});

it('fails assertReacted and assertNothingReacted correctly', function (): void {
    $fake = Likes::fake();

    $fake->assertNothingReacted();
    expect(fn () => $fake->assertReacted($this->post))->toThrow(AssertionFailedError::class);

    $this->actor->react($this->post, 'love');

    $fake->assertReacted($this->post, 'love');
    expect(fn () => $fake->assertNothingReacted())->toThrow(AssertionFailedError::class)
        ->and(fn () => $fake->assertReacted($this->post, 'like'))->toThrow(AssertionFailedError::class);
});

it('fails the like assertions correctly for calls made through the trait', function (): void {
    $fake = Likes::fake();

    expect(fn () => $fake->assertLiked($this->post))->toThrow(AssertionFailedError::class)
        ->and(fn () => $fake->assertLikedBy($this->actor, $this->post))->toThrow(AssertionFailedError::class);

    $this->actor->like($this->post);

    $fake->assertNotLiked($this->other);
    expect(fn () => $fake->assertNotLiked($this->post))->toThrow(AssertionFailedError::class)
        ->and(fn () => $fake->assertNothingLiked())->toThrow(AssertionFailedError::class)
        ->and(fn () => $fake->assertLikedCount(2))->toThrow(AssertionFailedError::class)
        ->and(fn () => $fake->assertLikedTimes($this->post, 2))->toThrow(AssertionFailedError::class);
});
