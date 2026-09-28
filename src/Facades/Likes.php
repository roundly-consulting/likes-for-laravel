<?php

declare(strict_types=1);

namespace RoundlyConsulting\Likes\Facades;

use Illuminate\Support\Facades\Facade;
use RoundlyConsulting\Likes\LikeManager;
use RoundlyConsulting\Likes\Testing\LikesFake;

/**
 * @method static \RoundlyConsulting\Likes\PendingLike actor(\Illuminate\Database\Eloquent\Model $actor)
 * @method static \RoundlyConsulting\Likes\PendingLike as(string $type)
 * @method static bool like(\Illuminate\Database\Eloquent\Model $likeable)
 * @method static bool unlike(\Illuminate\Database\Eloquent\Model $likeable)
 * @method static bool toggle(\Illuminate\Database\Eloquent\Model $likeable)
 * @method static bool react(\Illuminate\Database\Eloquent\Model $likeable)
 * @method static bool has(\Illuminate\Database\Eloquent\Model $likeable)
 * @method static void likeMany(iterable<\Illuminate\Database\Eloquent\Model> $likeables)
 * @method static void unlikeMany(iterable<\Illuminate\Database\Eloquent\Model> $likeables)
 * @method static \RoundlyConsulting\Likes\LikeableLikes for(\Illuminate\Database\Eloquent\Model $likeable)
 * @method static LikesFake fake()
 * @method static void assertLiked(\Illuminate\Database\Eloquent\Model $likeable, ?string $type = null)
 * @method static void assertNotLiked(\Illuminate\Database\Eloquent\Model $likeable)
 * @method static void assertLikedBy(\Illuminate\Database\Eloquent\Model $actor, \Illuminate\Database\Eloquent\Model $likeable, ?string $type = null)
 * @method static void assertNothingLiked()
 * @method static void assertLikedCount(int $count)
 * @method static void assertLikedTimes(\Illuminate\Database\Eloquent\Model $likeable, int $count)
 * @method static void assertUnliked(\Illuminate\Database\Eloquent\Model $likeable, ?string $type = null, ?\Illuminate\Database\Eloquent\Model $by = null)
 * @method static void assertNothingUnliked()
 * @method static void assertReacted(\Illuminate\Database\Eloquent\Model $likeable, ?string $type = null, ?\Illuminate\Database\Eloquent\Model $by = null)
 * @method static void assertNothingReacted()
 *
 * @see LikeManager
 */
final class Likes extends Facade
{
    /**
     * Swap the manager (facade and container) for a recording fake and return it. The fake still
     * performs every operation against the database; it records them for the `assert*()` helpers.
     */
    public static function fake(): LikesFake
    {
        self::swap($fake = new LikesFake);

        return $fake;
    }

    protected static function getFacadeAccessor(): string
    {
        return LikeManager::class;
    }
}
