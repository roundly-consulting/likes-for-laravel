<?php

declare(strict_types=1);

namespace RoundlyConsulting\Likes\Events;

use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use RoundlyConsulting\Likes\Events\Concerns\BroadcastsLikeEvent;
use RoundlyConsulting\Likes\Models\Like;

/**
 * Fired when an actor switches an existing reaction in place (e.g. like → love).
 * Carries both the previous and new reaction type so listeners can handle the
 * transition without double-counting a separate unlike + like.
 */
final class ReactionChanged implements ShouldBroadcast
{
    use BroadcastsLikeEvent;
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public Model $actor,
        public Model $likeable,
        public string $from,
        public string $to,
        public Like $like,
    ) {}

    public function broadcastAs(): string
    {
        return 'reaction.changed';
    }

    protected function broadcastable(): Model
    {
        return $this->likeable;
    }
}
