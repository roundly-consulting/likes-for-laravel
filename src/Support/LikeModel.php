<?php

declare(strict_types=1);

namespace RoundlyConsulting\Likes\Support;

use RoundlyConsulting\Likes\Models\Like;
use RoundlyConsulting\PackageToolkit\Support\ModelResolver;

/**
 * Resolves the Eloquent model backing likes from `likes.model`.
 *
 * Absent config resolves the packaged model; anything else must be that model or a subclass of
 * it, or the toolkit's ModelResolver throws InvalidConfigurationException naming the key — a
 * foreign class is never silently replaced.
 */
final class LikeModel
{
    /**
     * @return class-string<Like>
     */
    public static function class(): string
    {
        return ModelResolver::for('likes.model', Like::class);
    }
}
