<?php

declare(strict_types=1);

namespace RoundlyConsulting\Likes\Testing;

use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\Assert;
use RoundlyConsulting\Likes\LikeManager;
use RoundlyConsulting\Likes\PendingLike;

/**
 * A recording, still-performing variant of {@see LikeManager} for host-app
 * tests. Operations run against the database as usual while assertions verify
 * what happened, matching Laravel's *::fake() ergonomics.
 */
final class LikesFake extends LikeManager
{
    /** @var list<RecordedLike> */
    private array $recorded = [];

    public function actor(Model $actor): PendingLike
    {
        return (new RecordingPendingLike($this))->actor($actor);
    }

    public function as(string $type): PendingLike
    {
        return (new RecordingPendingLike($this))->as($type);
    }

    public function like(Model $likeable): bool
    {
        return (new RecordingPendingLike($this))->like($likeable);
    }

    public function unlike(Model $likeable): bool
    {
        return (new RecordingPendingLike($this))->unlike($likeable);
    }

    public function toggle(Model $likeable): bool
    {
        return (new RecordingPendingLike($this))->toggle($likeable);
    }

    public function react(Model $likeable): bool
    {
        return (new RecordingPendingLike($this))->react($likeable);
    }

    /**
     * Record a performed operation. Called by {@see RecordingPendingLike}.
     *
     * @internal
     */
    public function record(string $operation, Model $actor, Model $likeable, ?string $type): void
    {
        $this->recorded[] = new RecordedLike($operation, $actor, $likeable, $type);
    }

    public function assertLiked(Model $likeable, ?string $type = null): void
    {
        Assert::assertTrue(
            $this->hasOperation('like', $likeable, null, $type),
            'Expected a like to be recorded for the model, but none was.',
        );
    }

    public function assertNotLiked(Model $likeable): void
    {
        Assert::assertFalse(
            $this->hasOperation('like', $likeable),
            'Expected no like to be recorded for the model, but one was.',
        );
    }

    public function assertLikedBy(Model $actor, Model $likeable, ?string $type = null): void
    {
        Assert::assertTrue(
            $this->hasOperation('like', $likeable, $actor, $type),
            'Expected the actor to have liked the model, but no such like was recorded.',
        );
    }

    public function assertNothingLiked(): void
    {
        Assert::assertEmpty(
            array_filter($this->recorded, static fn (RecordedLike $r): bool => $r->operation === 'like'),
            'Expected no likes to be recorded, but some were.',
        );
    }

    public function assertLikedCount(int $count): void
    {
        Assert::assertCount(
            $count,
            array_filter($this->recorded, static fn (RecordedLike $r): bool => $r->operation === 'like'),
            "Expected [{$count}] likes to be recorded.",
        );
    }

    public function assertLikedTimes(Model $likeable, int $count): void
    {
        $matches = array_filter(
            $this->recorded,
            static fn (RecordedLike $r): bool => $r->operation === 'like' && $r->matches($likeable),
        );

        Assert::assertCount(
            $count,
            $matches,
            "Expected the model to be liked [{$count}] times.",
        );
    }

    private function hasOperation(string $operation, Model $likeable, ?Model $actor = null, ?string $type = null): bool
    {
        foreach ($this->recorded as $record) {
            if ($record->operation !== $operation) {
                continue;
            }

            if (! $record->matches($likeable, $type)) {
                continue;
            }

            if ($actor !== null && ! $record->byActor($actor)) {
                continue;
            }

            return true;
        }

        return false;
    }
}
