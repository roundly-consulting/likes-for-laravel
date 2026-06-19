<?php

declare(strict_types=1);

namespace RoundlyConsulting\Likes\Events;

use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use RoundlyConsulting\Likes\Events\Concerns\BroadcastsLikeEvent;
use RoundlyConsulting\Likes\Models\Like;

final class Liked implements ShouldBroadcast
{
    use BroadcastsLikeEvent;
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public Model $actor,
        public Model $likeable,
        public string $type,
        public Like $like,
    ) {}

    public function broadcastAs(): string
    {
        return 'like.created';
    }

    protected function broadcastable(): Model
    {
        return $this->likeable;
    }
}
