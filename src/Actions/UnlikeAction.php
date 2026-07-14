<?php

declare(strict_types=1);

namespace RoundlyConsulting\Likes\Actions;

use RoundlyConsulting\Likes\DataTransferObjects\LikeData;
use RoundlyConsulting\Likes\Events\LikeToggled;
use RoundlyConsulting\Likes\Events\Unliked;
use RoundlyConsulting\Likes\Models\Like;
use RoundlyConsulting\Likes\Support\LikeModel;

final class UnlikeAction
{
    /**
     * Ensure the actor does not like the likeable. Idempotent: a no-op if the
     * like is already absent.
     *
     * Returns false (no like exists) for symmetry with toggleLike().
     */
    public function execute(LikeData $data): bool
    {
        $model = LikeModel::class();

        /** @var Like|null $like */
        $like = $model::query()
            ->whereMorphedTo('actor', $data->actor)
            ->whereMorphedTo('likeable', $data->likeable)
            ->where('type', $data->type)
            ->first();

        if ($like === null) {
            return false;
        }

        $like->delete();

        Unliked::dispatch($data->actor, $data->likeable, $data->type, $like);
        LikeToggled::dispatch($data->actor, $data->likeable, false);

        return false;
    }
}
