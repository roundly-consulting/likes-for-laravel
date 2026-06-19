<?php

declare(strict_types=1);

namespace RoundlyConsulting\Likes\Actions;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Likes\DataTransferObjects\LikeData;

final class UnlikeManyAction
{
    public function __construct(
        private readonly UnlikeAction $unlike,
    ) {}

    /**
     * Unlike every given target as the actor. Idempotent per target; fires an
     * Unliked event per removed like.
     *
     * @param  iterable<Model>  $likeables
     */
    public function execute(Model $actor, iterable $likeables, ?string $type = null): void
    {
        foreach ($likeables as $likeable) {
            $this->unlike->execute(new LikeData($actor, $likeable, $type));
        }
    }
}
