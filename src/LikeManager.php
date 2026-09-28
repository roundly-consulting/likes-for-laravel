<?php

declare(strict_types=1);

namespace RoundlyConsulting\Likes;

use Illuminate\Database\Eloquent\Model;

/**
 * The `Likes` facade root. Writes go through a {@see PendingLike} (as the resolved actor, or an
 * explicit `actor()` / reaction type `as()`); reads for one likeable go through `for()`. Model
 * traits (`GivesLikes`, `HasLikes`) delegate here, so `Likes::fake()` sees every call.
 */
class LikeManager
{
    /**
     * Start a fluent chain for a specific actor.
     */
    public function actor(Model $actor): PendingLike
    {
        return $this->pending()->actor($actor);
    }

    /**
     * Start a fluent chain for a specific reaction type.
     */
    public function as(string $type): PendingLike
    {
        return $this->pending()->as($type);
    }

    /**
     * Like the model as the resolved actor (auth user by default).
     */
    public function like(Model $likeable): bool
    {
        return $this->pending()->like($likeable);
    }

    /**
     * Unlike the model as the resolved actor.
     */
    public function unlike(Model $likeable): bool
    {
        return $this->pending()->unlike($likeable);
    }

    /**
     * Toggle the like for the model as the resolved actor.
     */
    public function toggle(Model $likeable): bool
    {
        return $this->pending()->toggle($likeable);
    }

    /**
     * React to the model as the resolved actor, switching any existing
     * reaction in place (one active reaction per actor + likeable).
     */
    public function react(Model $likeable): bool
    {
        return $this->pending()->react($likeable);
    }

    /**
     * Whether the resolved actor likes the model.
     */
    public function has(Model $likeable): bool
    {
        return $this->pending()->has($likeable);
    }

    /**
     * @param  iterable<Model>  $likeables
     */
    public function likeMany(iterable $likeables): void
    {
        $this->pending()->likeMany($likeables);
    }

    /**
     * @param  iterable<Model>  $likeables
     */
    public function unlikeMany(iterable $likeables): void
    {
        $this->pending()->unlikeMany($likeables);
    }

    /**
     * Read the likes of one likeable: counts, the reaction breakdown, who liked it.
     */
    public function for(Model $likeable): LikeableLikes
    {
        return new LikeableLikes($likeable);
    }

    /**
     * The builder every write starts from. The fake returns a recording one.
     */
    protected function pending(): PendingLike
    {
        return new PendingLike;
    }
}
