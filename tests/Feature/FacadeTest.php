<?php

declare(strict_types=1);

use RoundlyConsulting\Likes\Exceptions\NoAuthenticatedActorException;
use RoundlyConsulting\Likes\Facades\Likes;
use RoundlyConsulting\Likes\LikeManager;
use RoundlyConsulting\Likes\Models\Like;
use RoundlyConsulting\Likes\Tests\Models\ActorTestModel;
use RoundlyConsulting\Likes\Tests\Models\CurrentActorResolver;
use RoundlyConsulting\Likes\Tests\Models\PostTestModel;

beforeEach(function (): void {
    $this->actor = ActorTestModel::create();
    $this->post = PostTestModel::create();
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
