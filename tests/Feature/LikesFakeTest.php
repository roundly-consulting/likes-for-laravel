<?php

declare(strict_types=1);

use PHPUnit\Framework\AssertionFailedError;
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

it('records a react as a like', function (): void {
    $fake = Likes::fake();

    Likes::actor($this->actor)->as('love')->react($this->post);

    $fake->assertLiked($this->post, 'love');
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

    $fake->assertLiked($this->other);
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
