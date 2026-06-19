<?php

declare(strict_types=1);

namespace RoundlyConsulting\Likes;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Facade;
use RoundlyConsulting\Likes\Testing\LikesFake;

class LikeManager
{
    /**
     * Swap the manager for a recording fake and return it for assertions.
     */
    public static function fake(): LikesFake
    {
        $fake = new LikesFake;

        app()->instance(self::class, $fake);
        Facade::clearResolvedInstance(self::class);

        return $fake;
    }

    /**
     * Start a fluent chain for a specific actor.
     */
    public function actor(Model $actor): PendingLike
    {
        return (new PendingLike)->actor($actor);
    }

    /**
     * Start a fluent chain for a specific reaction type.
     */
    public function as(string $type): PendingLike
    {
        return (new PendingLike)->as($type);
    }

    /**
     * Like the model as the resolved actor (auth user by default).
     */
    public function like(Model $likeable): bool
    {
        return (new PendingLike)->like($likeable);
    }

    /**
     * Unlike the model as the resolved actor.
     */
    public function unlike(Model $likeable): bool
    {
        return (new PendingLike)->unlike($likeable);
    }

    /**
     * Toggle the like for the model as the resolved actor.
     */
    public function toggle(Model $likeable): bool
    {
        return (new PendingLike)->toggle($likeable);
    }

    /**
     * React to the model as the resolved actor, switching any existing
     * reaction in place (one active reaction per actor + likeable).
     */
    public function react(Model $likeable): bool
    {
        return (new PendingLike)->react($likeable);
    }

    /**
     * Whether the resolved actor likes the model.
     */
    public function has(Model $likeable): bool
    {
        return (new PendingLike)->has($likeable);
    }

    /**
     * @param  iterable<Model>  $likeables
     */
    public function likeMany(iterable $likeables): void
    {
        (new PendingLike)->likeMany($likeables);
    }

    /**
     * @param  iterable<Model>  $likeables
     */
    public function unlikeMany(iterable $likeables): void
    {
        (new PendingLike)->unlikeMany($likeables);
    }
}
