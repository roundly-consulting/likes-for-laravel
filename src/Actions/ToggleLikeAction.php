<?php

declare(strict_types=1);

namespace RoundlyConsulting\Likes\Actions;

use RoundlyConsulting\Likes\DataTransferObjects\LikeData;
use RoundlyConsulting\Likes\Models\Like;

final class ToggleLikeAction
{
    public function __construct(
        private readonly LikeAction $like,
        private readonly UnlikeAction $unlike,
    ) {}

    /**
     * Toggle the like for the actor/likeable/type. Returns true when a like
     * now exists, false when it was removed.
     */
    public function execute(LikeData $data): bool
    {
        /** @var class-string<Like> $model */
        $model = config('likes.model', Like::class);

        $liked = $model::query()
            ->whereMorphedTo('actor', $data->actor)
            ->whereMorphedTo('likeable', $data->likeable)
            ->where('type', $data->type)
            ->exists();

        return $liked
            ? $this->unlike->execute($data)
            : $this->like->execute($data);
    }
}
