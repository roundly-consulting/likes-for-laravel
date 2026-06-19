<?php

declare(strict_types=1);

namespace RoundlyConsulting\Likes\Testing;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Likes\Actions\LikeAction;
use RoundlyConsulting\Likes\Actions\ToggleLikeAction;
use RoundlyConsulting\Likes\Actions\UnlikeAction;
use RoundlyConsulting\Likes\DataTransferObjects\LikeData;

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
        return app(LikeAction::class)->execute(
            new LikeData($this->liker($actor), $likeable, $type),
        );
    }

    public function unlikeAs(Model $likeable, ?string $type = null, ?Model $actor = null): bool
    {
        return app(UnlikeAction::class)->execute(
            new LikeData($this->liker($actor), $likeable, $type),
        );
    }

    public function toggleAs(Model $likeable, ?string $type = null, ?Model $actor = null): bool
    {
        return app(ToggleLikeAction::class)->execute(
            new LikeData($this->liker($actor), $likeable, $type),
        );
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
