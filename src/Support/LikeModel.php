<?php

declare(strict_types=1);

namespace RoundlyConsulting\Likes\Support;

use RoundlyConsulting\Likes\Models\Like;
use RoundlyConsulting\PackageToolkit\Support\ModelResolver;

/**
 * Resolves the Eloquent model backing likes from `likes.model`.
 *
 * The toolkit's ModelResolver validates that the configured value is a real
 * Eloquent model; anything that isn't a Like (so it can't answer the package's
 * queries) falls back to the packaged model.
 */
final class LikeModel
{
    /**
     * @return class-string<Like>
     */
    public static function class(): string
    {
        $model = ModelResolver::for('likes.model', Like::class);

        return is_a($model, Like::class, true) ? $model : Like::class;
    }
}
