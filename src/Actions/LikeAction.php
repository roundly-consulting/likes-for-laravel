<?php

declare(strict_types=1);

namespace RoundlyConsulting\Likes\Actions;

use RoundlyConsulting\Likes\DataTransferObjects\LikeData;
use RoundlyConsulting\Likes\Events\Liked;
use RoundlyConsulting\Likes\Support\LikeRows;

final class LikeAction
{
    /**
     * Ensure the actor likes the likeable. Idempotent, also under concurrency: a no-op if
     * already liked, restoring a soft-deleted row instead of creating a duplicate, and a
     * concurrent duplicate request is refused by the unique index — so exactly one `Liked`
     * fires per real change.
     *
     * Returns true (a like now exists) for symmetry with toggleLike().
     */
    public function execute(LikeData $data): bool
    {
        $like = LikeRows::activate($data);

        if ($like !== null) {
            Liked::dispatch($data->actor, $data->likeable, $data->type, $like);
        }

        return true;
    }
}
