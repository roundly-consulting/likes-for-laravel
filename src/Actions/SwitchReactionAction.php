<?php

declare(strict_types=1);

namespace RoundlyConsulting\Likes\Actions;

use RoundlyConsulting\Likes\DataTransferObjects\LikeData;
use RoundlyConsulting\Likes\Events\Liked;
use RoundlyConsulting\Likes\Events\ReactionChanged;
use RoundlyConsulting\Likes\Models\Like;
use RoundlyConsulting\Likes\Support\LikeModel;

/**
 * Reacts to a likeable while keeping exactly one active reaction per
 * actor + likeable. Unlike like(), which lets multiple typed reactions
 * co-exist, react() collapses to a single row and switches it in place.
 */
final class SwitchReactionAction
{
    /**
     * @return bool true — a reaction now exists
     */
    public function execute(LikeData $data): bool
    {
        $model = LikeModel::class();

        /** @var Like|null $existing */
        $existing = $model::withTrashed()
            ->whereMorphedTo('actor', $data->actor)
            ->whereMorphedTo('likeable', $data->likeable)
            ->orderByDesc('id')
            ->first();

        // No prior reaction (or only soft-deleted): behave like a fresh like.
        if ($existing === null || $existing->trashed()) {
            return $this->createOrRestore($model, $data, $existing);
        }

        // Same reaction already active: nothing to do.
        if ($existing->type === $data->type) {
            return true;
        }

        $from = $existing->type;

        $existing->update(['type' => $data->type]);

        ReactionChanged::dispatch($data->actor, $data->likeable, $from, $data->type, $existing);

        return true;
    }

    private function createOrRestore(string $model, LikeData $data, ?Like $existing): bool
    {
        if ($existing !== null && $existing->trashed()) {
            $existing->restore();
            $existing->update(['type' => $data->type]);

            $this->dispatchLiked($data, $existing);

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

        $this->dispatchLiked($data, $like);

        return true;
    }

    private function dispatchLiked(LikeData $data, Like $like): void
    {
        Liked::dispatch($data->actor, $data->likeable, $data->type, $like);
    }
}
