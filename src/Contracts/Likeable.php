<?php

declare(strict_types=1);

namespace RoundlyConsulting\Likes\Contracts;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Likes\DataTransferObjects\ReactionSummary;

/**
 * Implemented by models that use the HasLikes trait. Lets the API surface
 * (resources, expectations) reference the trait's public methods through a real
 * type instead of the trait itself.
 */
interface Likeable
{
    public function isLikedBy(Model $actor, ?string $type = null): bool;

    public function likesCount(?string $type = null): int;

    public function reactionSummary(?Model $viewer = null): ReactionSummary;

    /**
     * @return array{count: int, viewer_state: array{liked: bool, reaction: ?string}, breakdown: array<string, int>}
     */
    public function toLikeArray(?Model $viewer = null): array;
}
