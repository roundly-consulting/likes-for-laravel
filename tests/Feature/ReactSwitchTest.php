<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Likes\Events\Liked;
use RoundlyConsulting\Likes\Events\ReactionChanged;
use RoundlyConsulting\Likes\Events\Unliked;
use RoundlyConsulting\Likes\Exceptions\InvalidReactionTypeException;
use RoundlyConsulting\Likes\Facades\Likes;
use RoundlyConsulting\Likes\Models\Like;
use RoundlyConsulting\Likes\Tests\Models\ActorTestModel;
use RoundlyConsulting\Likes\Tests\Models\CurrentActorResolver;
use RoundlyConsulting\Likes\Tests\Models\PostTestModel;

beforeEach(function (): void {
    config()->set('likes.reactions', ['like', 'love', 'wow']);
    config()->set('likes.default_reaction', 'like');

    $this->actor = ActorTestModel::query()->create();
    $this->post = PostTestModel::query()->create();
});

it('react creates a like when none exists', function (): void {
    Event::fake();

    $result = $this->actor->react($this->post, 'love');

    expect($result)->toBeTrue()
        ->and(Like::query()->whereNull('deleted_at')->count())->toBe(1);

    Event::assertDispatched(Liked::class);
    Event::assertNotDispatched(ReactionChanged::class);
});

it('react with same type is a no-op', function (): void {
    $this->actor->react($this->post, 'love');

    Event::fake();

    $result = $this->actor->react($this->post, 'love');

    expect($result)->toBeTrue()
        ->and(Like::query()->whereNull('deleted_at')->count())->toBe(1);

    Event::assertNotDispatched(Liked::class);
    Event::assertNotDispatched(ReactionChanged::class);
});

it('react switches the existing row in place', function (): void {
    $this->actor->react($this->post, 'like');

    $this->actor->react($this->post, 'love');

    expect(Like::query()->whereNull('deleted_at')->count())->toBe(1)
        ->and(Like::query()->whereNull('deleted_at')->first()?->type)->toBe('love');
});

it('switch fires ReactionChanged with from and to and nothing else', function (): void {
    $this->actor->react($this->post, 'like');

    Event::fake();

    $this->actor->react($this->post, 'love');

    Event::assertDispatched(ReactionChanged::class, function (ReactionChanged $event): bool {
        return $event->from === 'like' && $event->to === 'love';
    });
    Event::assertNotDispatched(Liked::class);
    Event::assertNotDispatched(Unliked::class);
});

it('react rejects an invalid type', function (): void {
    $this->actor->react($this->post, 'nope');
})->throws(InvalidReactionTypeException::class);

it('react restores a soft-deleted row on the create branch', function (): void {
    $this->actor->react($this->post, 'like');
    $this->actor->unlike($this->post, 'like');

    expect(Like::query()->whereNull('deleted_at')->count())->toBe(0);

    $this->actor->react($this->post, 'love');

    expect(Like::query()->whereNull('deleted_at')->count())->toBe(1)
        ->and(Like::withTrashed()->count())->toBe(1)
        ->and(Like::query()->whereNull('deleted_at')->first()?->type)->toBe('love');
});

it('switches in place via the facade and the manager', function (): void {
    Likes::actor($this->actor)->as('like')->react($this->post);
    Likes::actor($this->actor)->as('love')->react($this->post);

    expect(Like::query()->whereNull('deleted_at')->count())->toBe(1)
        ->and(Like::query()->whereNull('deleted_at')->first()?->type)->toBe('love');
});

it('switches in place via switchReaction alias', function (): void {
    $this->actor->switchReaction($this->post, 'like');
    $this->actor->switchReaction($this->post, 'wow');

    expect(Like::query()->whereNull('deleted_at')->count())->toBe(1)
        ->and(Like::query()->whereNull('deleted_at')->first()?->type)->toBe('wow');
});

it('reacts as the resolved actor via the manager', function (): void {
    config()->set('likes.actor_resolver', CurrentActorResolver::class);

    Likes::as('love')->react($this->post);

    expect(Like::query()->whereNull('deleted_at')->first()?->type)->toBe('love');
});
