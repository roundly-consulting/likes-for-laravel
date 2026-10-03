<?php

declare(strict_types=1);

namespace RoundlyConsulting\Likes\Support;

use Illuminate\Database\Eloquent\Model;

/**
 * Resolves the acting model the same way the fluent builder does, but never
 * throws: callers that must render for guests (feeds, summaries, resources)
 * rely on a null return instead of an exception.
 */
final class ActorResolver
{
    public static function resolve(?Model $actor = null): ?Model
    {
        if ($actor instanceof Model) {
            return $actor;
        }

        $resolver = LikesConfig::actorResolver();

        if ($resolver === null) {
            $user = auth()->user();

            return $user instanceof Model ? $user : null;
        }

        $result = $resolver();

        return $result instanceof Model ? $result : null;
    }
}
