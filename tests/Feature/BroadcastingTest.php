<?php

declare(strict_types=1);

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Likes\Events\Liked;
use RoundlyConsulting\Likes\Events\ReactionChanged;
use RoundlyConsulting\Likes\Events\Unliked;
use RoundlyConsulting\Likes\Models\Like;
use RoundlyConsulting\Likes\Tests\Models\ActorTestModel;
use RoundlyConsulting\Likes\Tests\Models\PostTestModel;

function makeLikedEvent(): Liked
{
    $actor = ActorTestModel::query()->create();
    $post = PostTestModel::query()->create();
    $like = Like::query()->create([
        'actor_id' => $actor->getKey(),
        'actor_type' => $actor->getMorphClass(),
        'likeable_id' => $post->getKey(),
        'likeable_type' => $post->getMorphClass(),
        'type' => 'like',
    ]);

    return new Liked($actor, $post, 'like', $like);
}

it('does not broadcast when disabled (default)', function (): void {
    config()->set('likes.broadcast.enabled', false);

    expect(makeLikedEvent()->broadcastWhen())->toBeFalse();
});

it('broadcasts when enabled', function (): void {
    config()->set('likes.broadcast.enabled', true);

    expect(makeLikedEvent()->broadcastWhen())->toBeTrue();
});

it('returns stable broadcastAs names', function (): void {
    $actor = ActorTestModel::query()->create();
    $post = PostTestModel::query()->create();
    $like = Like::query()->create([
        'actor_id' => $actor->getKey(),
        'actor_type' => $actor->getMorphClass(),
        'likeable_id' => $post->getKey(),
        'likeable_type' => $post->getMorphClass(),
        'type' => 'like',
    ]);

    expect((new Liked($actor, $post, 'like', $like))->broadcastAs())->toBe('like.created')
        ->and((new Unliked($actor, $post, 'like', $like))->broadcastAs())->toBe('like.removed')
        ->and((new ReactionChanged($actor, $post, 'like', 'love', $like))->broadcastAs())->toBe('reaction.changed');
});

it('builds a private channel by default', function (): void {
    $channel = makeLikedEvent()->broadcastOn();

    expect($channel)->toBeInstanceOf(PrivateChannel::class)
        ->and($channel->name)->toContain('likes.post_test_models.');
});

it('honours the configured prefix and public channel type', function (): void {
    config()->set('likes.broadcast.channel_prefix', 'reactions');
    config()->set('likes.broadcast.channel_type', 'public');

    $channel = makeLikedEvent()->broadcastOn();

    expect($channel)->toBeInstanceOf(Channel::class)
        ->and($channel->name)->toContain('reactions.post_test_models.');
});

it('builds a presence channel when configured', function (): void {
    config()->set('likes.broadcast.channel_type', 'presence');

    expect(makeLikedEvent()->broadcastOn())->toBeInstanceOf(PresenceChannel::class);
});

it('builds channels for unliked and reaction-changed events', function (): void {
    $actor = ActorTestModel::query()->create();
    $post = PostTestModel::query()->create();
    $like = Like::query()->create([
        'actor_id' => $actor->getKey(),
        'actor_type' => $actor->getMorphClass(),
        'likeable_id' => $post->getKey(),
        'likeable_type' => $post->getMorphClass(),
        'type' => 'like',
    ]);

    expect((new Unliked($actor, $post, 'like', $like))->broadcastOn())
        ->toBeInstanceOf(PrivateChannel::class)
        ->and((new ReactionChanged($actor, $post, 'like', 'love', $like))->broadcastOn())
        ->toBeInstanceOf(PrivateChannel::class);
});

it('still dispatches plainly when broadcasting is enabled', function (): void {
    config()->set('likes.broadcast.enabled', true);

    Event::fake();

    $actor = ActorTestModel::query()->create();
    $post = PostTestModel::query()->create();

    $actor->like($post);

    Event::assertDispatched(Liked::class);
});
