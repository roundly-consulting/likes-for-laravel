<?php

declare(strict_types=1);

namespace RoundlyConsulting\Likes\Facades;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Facade;
use RoundlyConsulting\Likes\LikeManager;
use RoundlyConsulting\Likes\PendingLike;

/**
 * @method static PendingLike actor(Model $actor)
 * @method static PendingLike as(string $type)
 * @method static bool like(Model $likeable)
 * @method static bool unlike(Model $likeable)
 * @method static bool toggle(Model $likeable)
 * @method static bool react(Model $likeable)
 * @method static bool has(Model $likeable)
 * @method static void likeMany(iterable<Model> $likeables)
 * @method static void unlikeMany(iterable<Model> $likeables)
 * @method static \RoundlyConsulting\Likes\Testing\LikesFake fake()
 *
 * @see LikeManager
 */
final class Likes extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return LikeManager::class;
    }
}
