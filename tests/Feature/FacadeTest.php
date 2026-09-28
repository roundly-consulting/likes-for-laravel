<?php

declare(strict_types=1);

use RoundlyConsulting\Likes\Exceptions\NoAuthenticatedActorException;
use RoundlyConsulting\Likes\Facades\Likes;
use RoundlyConsulting\Likes\LikeableLikes;
use RoundlyConsulting\Likes\LikeManager;
use RoundlyConsulting\Likes\Models\Like;
use RoundlyConsulting\Likes\Tests\Models\ActorTestModel;
use RoundlyConsulting\Likes\Tests\Models\CommentTestModel;
use RoundlyConsulting\Likes\Tests\Models\CurrentActorResolver;
use RoundlyConsulting\Likes\Tests\Models\PostTestModel;

beforeEach(function (): void {
    $this->actor = ActorTestModel::create();
    $this->post = PostTestModel::create();
});

it('documents its root, is fakeable and reaches every action', function (): void {
    expect(Likes::class)
        ->toDocumentItsRoot()
        ->toBeFakeable()
        ->toReachEveryAction(__DIR__.'/../../src/Actions');
});

it('likes as an explicit actor', function (): void {
    expect(Likes::actor($this->actor)->like($this->post))->toBeTrue()
        ->and(Likes::actor($this->actor)->has($this->post))->toBeTrue();
});

it('toggles and unlikes as an explicit actor', function (): void {
    Likes::actor($this->actor)->toggle($this->post);
    expect(Likes::actor($this->actor)->has($this->post))->toBeTrue();

    Likes::actor($this->actor)->unlike($this->post);
    expect(Likes::actor($this->actor)->has($this->post))->toBeFalse();
});

it('likes as the authenticated actor', function (): void {
    $this->actingAs($this->actor);

    expect(Likes::like($this->post))->toBeTrue()
        ->and(Likes::has($this->post))->toBeTrue();
});

it('toggles as the authenticated actor', function (): void {
    $this->actingAs($this->actor);

    expect(Likes::toggle($this->post))->toBeTrue();
});

it('throws when no actor is resolvable', function (): void {
    Likes::like($this->post);
})->throws(NoAuthenticatedActorException::class);

it('resolves a custom actor resolver from config', function (): void {
    $actor = $this->actor;
    config()->set('likes.actor_resolver', fn () => $actor);

    expect(Likes::like($this->post))->toBeTrue()
        ->and(Like::query()->count())->toBe(1);
});

it('resolves an invokable class-string resolver', function (): void {
    config()->set('likes.actor_resolver', CurrentActorResolver::class);

    expect(Likes::like($this->post))->toBeTrue()
        ->and(Like::query()->count())->toBe(1);
});

it('throws when a non-callable resolver yields no actor', function (): void {
    config()->set('likes.actor_resolver', 'not-a-callable-or-class');

    Likes::like($this->post);
})->throws(NoAuthenticatedActorException::class);

it('unlikes via the manager as the authenticated actor', function (): void {
    $this->actingAs($this->actor);
    Likes::like($this->post);

    expect(Likes::unlike($this->post))->toBeFalse()
        ->and(Likes::has($this->post))->toBeFalse();
});

it('selects a reaction type via as()', function (): void {
    config()->set('likes.reactions', ['like', 'love']);

    Likes::actor($this->actor)->as('love')->like($this->post);

    expect(Like::query()->where('type', 'love')->count())->toBe(1);
});

it('bulk likes and unlikes as the authenticated actor', function (): void {
    $this->actingAs($this->actor);
    $second = PostTestModel::create();

    Likes::likeMany([$this->post, $second]);
    expect(Like::query()->count())->toBe(2);

    Likes::unlikeMany([$this->post, $second]);
    expect(Like::query()->count())->toBe(0);
});

it('binds the manager as a singleton', function (): void {
    expect(app(LikeManager::class))->toBe(app(LikeManager::class));
});

it('reads one likeable through Likes::for()', function (): void {
    config()->set('likes.reactions', ['like', 'love']);
    $other = ActorTestModel::create();

    Likes::actor($this->actor)->like($this->post);
    Likes::actor($other)->as('love')->like($this->post);

    $summary = Likes::for($this->post)->summary($this->actor);

    expect(Likes::for($this->post))->toBeInstanceOf(LikeableLikes::class)
        ->and(Likes::for($this->post)->count())->toBe(2)
        ->and(Likes::for($this->post)->count('love'))->toBe(1)
        ->and(Likes::for($this->post)->likedBy($this->actor))->toBeTrue()
        ->and(Likes::for($this->post)->likedBy($this->actor, 'love'))->toBeFalse()
        ->and(Likes::for($this->post)->likedBy($other, 'love'))->toBeTrue()
        ->and($summary->total)->toBe(2)
        ->and($summary->countFor('like'))->toBe(1)
        ->and($summary->countFor('love'))->toBe(1)
        ->and($summary->viewerReaction)->toBe('like');
});

it('scopes Likes::for() to exactly one likeable, never a same-id row of another type', function (): void {
    $comment = CommentTestModel::create(['id' => $this->post->getKey()]);

    Likes::actor($this->actor)->like($comment);

    expect(Likes::for($this->post)->count())->toBe(0)
        ->and(Likes::for($this->post)->likedBy($this->actor))->toBeFalse()
        ->and(Likes::for($this->post)->summary($this->actor)->total)->toBe(0)
        ->and(Likes::for($comment)->count())->toBe(1);
});

it('uses an eager-loaded likes_count for an untyped count', function (): void {
    Likes::actor($this->actor)->like($this->post);

    $loaded = PostTestModel::query()->withLikesCount()->findOrFail($this->post->getKey());
    $loaded->setAttribute('likes_count', 7);

    expect(Likes::for($loaded)->count())->toBe(7)
        ->and(Likes::for($loaded)->count('like'))->toBe(1);
});

it('summarises for the resolved viewer, and for a guest', function (): void {
    Likes::actor($this->actor)->like($this->post);

    expect(Likes::for($this->post)->summary()->viewerReaction)->toBeNull();

    $this->actingAs($this->actor);

    expect(Likes::for($this->post)->summary()->viewerReaction)->toBe('like');
});

it('serves the same API to an injected manager', function (): void {
    $manager = app(LikeManager::class);

    expect($manager)->toBe(Likes::getFacadeRoot())
        ->and($manager->actor($this->actor)->like($this->post))->toBeTrue()
        ->and($manager->for($this->post)->count())->toBe(1);
});

it('routes the model traits through the manager', function (): void {
    expect($this->actor->like($this->post))->toBeTrue()
        ->and($this->actor->hasLiked($this->post))->toBeTrue()
        ->and($this->post->likesCount())->toBe(1)
        ->and($this->post->hasBeenLikedBy($this->actor))->toBeTrue()
        ->and($this->post->reactionSummary($this->actor)->viewerReaction)->toBe('like');
});
