<?php

declare(strict_types=1);

namespace RoundlyConsulting\Likes\Events\Concerns;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithBroadcasting;
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Likes\Support\BroadcastChannel;
use RoundlyConsulting\PackageToolkit\Support\Config;

/**
 * Shared opt-in broadcasting behaviour for like events. Even though the events
 * implement ShouldBroadcast, broadcastWhen() returns the (default-false)
 * "likes.broadcast.enabled" flag, so nothing is broadcast unless a host opts in.
 *
 * The payload is ids and the reaction only. Without an explicit broadcastWith() Laravel
 * serialises every public property, which put the actor's whole model — email, phone,
 * anything not hidden — in front of every subscriber of the likeable's channel.
 */
trait BroadcastsLikeEvent
{
    use InteractsWithBroadcasting;

    public function broadcastWhen(): bool
    {
        return Config::boolean('likes.broadcast.enabled');
    }

    public function broadcastOn(): Channel
    {
        return BroadcastChannel::for($this->broadcastable());
    }

    abstract public function broadcastAs(): string;

    /**
     * @return array<string, mixed>
     */
    abstract public function broadcastWith(): array;

    abstract protected function broadcastable(): Model;

    /**
     * The ids every like event broadcasts: the like row, the actor and the likeable.
     *
     * @return array<string, mixed>
     */
    protected function broadcastIds(Model $actor, Model $likeable, Model $like): array
    {
        return [
            'like_id' => $like->getKey(),
            'actor_type' => $actor->getMorphClass(),
            'actor_id' => $actor->getKey(),
            'likeable_type' => $likeable->getMorphClass(),
            'likeable_id' => $likeable->getKey(),
        ];
    }
}
