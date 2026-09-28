<?php

declare(strict_types=1);

namespace RoundlyConsulting\Likes\Actions;

use RoundlyConsulting\Likes\DataTransferObjects\LikeData;
use RoundlyConsulting\Likes\Events\Unliked;
use RoundlyConsulting\Likes\Support\LikeRows;

final class UnlikeAction
{
    /**
     * Ensure the actor does not like the likeable. Idempotent, also under concurrency: a
     * no-op if the like is already absent, and of two racing unlikes only the one that
     * actually removed the row fires `Unliked`.
     *
     * Returns false (no like exists) for symmetry with toggleLike().
     */
    public function execute(LikeData $data): bool
    {
        $like = LikeRows::active($data);

        $removed = $like === null ? null : LikeRows::remove($like);

        if ($removed !== null) {
            Unliked::dispatch($data->actor, $data->likeable, $data->type, $removed);
        }

        return false;
    }
}
