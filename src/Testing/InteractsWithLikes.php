<?php

declare(strict_types=1);

namespace RoundlyConsulting\Likes\Testing;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Likes\LikeManager;
use RoundlyConsulting\Likes\PendingLike;

/**
 * Opt-in testing ergonomics for host applications. Use it from a Pest/PHPUnit
 * test case:
 *
 *     uses(RoundlyConsulting\Likes\Testing\InteractsWithLikes::class);
 *
 * It is intentionally framework-light and pulls in no runtime dependency on Pest.
 */
trait InteractsWithLikes
{
    private ?Model $actingLiker = null;

    /**
     * Remember an actor so subsequent helper calls can omit it.
     */
    public function actingAsLiker(Model $actor): static
    {
        $this->actingLiker = $actor;

        return $this;
    }

    public function likeAs(Model $likeable, ?string $type = null, ?Model $actor = null): bool
    {
        return $this->likesAsLiker($actor, $type)->like($likeable);
    }

    public function unlikeAs(Model $likeable, ?string $type = null, ?Model $actor = null): bool
    {
        return $this->likesAsLiker($actor, $type)->unlike($likeable);
    }

    public function toggleAs(Model $likeable, ?string $type = null, ?Model $actor = null): bool
    {
        return $this->likesAsLiker($actor, $type)->toggle($likeable);
    }

    /**
     * Through the manager, so a `Likes::fake()` in the same test records these calls too.
     */
    private function likesAsLiker(?Model $actor, ?string $type): PendingLike
    {
        $pending = app(LikeManager::class)->actor($this->liker($actor));

        return $type === null ? $pending : $pending->as($type);
    }

    private function liker(?Model $actor): Model
    {
        $liker = $actor ?? $this->actingLiker;

        if ($liker === null) {
            throw new \RuntimeException('No liker set. Call actingAsLiker() first or pass an actor.');
        }

        return $liker;
    }
}
