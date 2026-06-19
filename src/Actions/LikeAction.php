<?php

declare(strict_types=1);

namespace RoundlyConsulting\Likes\Actions;

use RoundlyConsulting\Likes\DataTransferObjects\LikeData;
use RoundlyConsulting\Likes\Events\Liked;
use RoundlyConsulting\Likes\Events\LikeToggled;
use RoundlyConsulting\Likes\Models\Like;

final class LikeAction
{
    /**
     * Ensure the actor likes the likeable. Idempotent: a no-op if already
     * liked, restoring a soft-deleted row instead of creating a duplicate.
     *
     * Returns true (a like now exists) for symmetry with toggleLike().
     */
    public function execute(LikeData $data): bool
    {
        /** @var class-string<Like> $model */
        $model = config('likes.model', Like::class);

        /** @var Like|null $existing */
        $existing = $model::withTrashed()
            ->whereMorphedTo('actor', $data->actor)
            ->whereMorphedTo('likeable', $data->likeable)
            ->where('type', $data->type)
            ->first();

        if ($existing !== null && $existing->trashed()) {
            $existing->restore();

            $this->dispatch($data, $existing);

            return true;
        }

        if ($existing !== null) {
            return true;
        }

        /** @var Like $like */
        $like = $model::query()->create([
            'actor_id' => $data->actor->getKey(),
            'actor_type' => $data->actor->getMorphClass(),
            'likeable_id' => $data->likeable->getKey(),
            'likeable_type' => $data->likeable->getMorphClass(),
            'type' => $data->type,
        ]);

        $this->dispatch($data, $like);

        return true;
    }

    private function dispatch(LikeData $data, Like $like): void
    {
        Liked::dispatch($data->actor, $data->likeable, $data->type, $like);
        LikeToggled::dispatch($data->actor, $data->likeable, true);
    }
}
