<?php

declare(strict_types=1);

namespace RoundlyConsulting\Likes\Events\Concerns;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithBroadcasting;
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Likes\Support\BroadcastChannel;

/**
 * Shared opt-in broadcasting behaviour for like events. Even though the events
 * implement ShouldBroadcast, broadcastWhen() returns the (default-false)
 * "likes.broadcast.enabled" flag, so nothing is broadcast unless a host opts in.
 */
trait BroadcastsLikeEvent
{
    use InteractsWithBroadcasting;

    public function broadcastWhen(): bool
    {
        return (bool) config('likes.broadcast.enabled', false);
    }

    public function broadcastOn(): Channel
    {
        return BroadcastChannel::for($this->broadcastable());
    }

    abstract public function broadcastAs(): string;

    abstract protected function broadcastable(): Model;
}
