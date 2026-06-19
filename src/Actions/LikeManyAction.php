<?php

declare(strict_types=1);

namespace RoundlyConsulting\Likes\Actions;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Likes\DataTransferObjects\LikeData;

final class LikeManyAction
{
    public function __construct(
        private readonly LikeAction $like,
    ) {}

    /**
     * Like every given target once as the actor. Idempotent per target;
     * fires a Liked event per newly created/restored like.
     *
     * @param  iterable<Model>  $likeables
     */
    public function execute(Model $actor, iterable $likeables, ?string $type = null): void
    {
        foreach ($likeables as $likeable) {
            $this->like->execute(new LikeData($actor, $likeable, $type));
        }
    }
}
