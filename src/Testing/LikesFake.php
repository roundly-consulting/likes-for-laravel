<?php

declare(strict_types=1);

namespace RoundlyConsulting\Likes\Testing;

use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\Assert;
use RoundlyConsulting\Likes\LikeManager;
use RoundlyConsulting\Likes\PendingLike;

/**
 * A recording, still-performing variant of {@see LikeManager} for host-app tests, installed by
 * `Likes::fake()`. Every write — flat, through `actor()`/`as()`, bulk, or from the
 * `GivesLikes` / `InteractsWithLikes` traits — runs through a {@see RecordingPendingLike}, so it
 * still hits the database (and `has()` / `for()` read that real state) while the `assert*()`
 * helpers verify what happened. A write is recorded only once it completes, so one the package
 * refuses (an unknown reaction type, no resolvable actor) is never recorded. A toggle is
 * recorded as the like or unlike it performed; bulk calls record one entry per model.
 */
final class LikesFake extends LikeManager
{
    /** @var list<RecordedLike> */
    private array $recorded = [];

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
            $this->hasOperation(RecordedLike::LIKE, $likeable, null, $type),
            'Expected a like to be recorded for the model, but none was.',
        );
    }

    public function assertNotLiked(Model $likeable): void
    {
        Assert::assertFalse(
            $this->hasOperation(RecordedLike::LIKE, $likeable),
            'Expected no like to be recorded for the model, but one was.',
        );
    }

    public function assertLikedBy(Model $actor, Model $likeable, ?string $type = null): void
    {
        Assert::assertTrue(
            $this->hasOperation(RecordedLike::LIKE, $likeable, $actor, $type),
            'Expected the actor to have liked the model, but no such like was recorded.',
        );
    }

    public function assertNothingLiked(): void
    {
        Assert::assertEmpty(
            $this->operations(RecordedLike::LIKE),
            'Expected no likes to be recorded, but some were.',
        );
    }

    public function assertLikedCount(int $count): void
    {
        Assert::assertCount(
            $count,
            $this->operations(RecordedLike::LIKE),
            "Expected [{$count}] likes to be recorded.",
        );
    }

    public function assertLikedTimes(Model $likeable, int $count): void
    {
        $matches = array_filter(
            $this->operations(RecordedLike::LIKE),
            static fn (RecordedLike $r): bool => $r->matches($likeable),
        );

        Assert::assertCount(
            $count,
            $matches,
            "Expected the model to be liked [{$count}] times.",
        );
    }

    public function assertUnliked(Model $likeable, ?string $type = null, ?Model $by = null): void
    {
        Assert::assertTrue(
            $this->hasOperation(RecordedLike::UNLIKE, $likeable, $by, $type),
            'Expected an unlike to be recorded for the model, but none was.',
        );
    }

    public function assertNothingUnliked(): void
    {
        Assert::assertEmpty(
            $this->operations(RecordedLike::UNLIKE),
            'Expected no unlikes to be recorded, but some were.',
        );
    }

    public function assertReacted(Model $likeable, ?string $type = null, ?Model $by = null): void
    {
        Assert::assertTrue(
            $this->hasOperation(RecordedLike::REACT, $likeable, $by, $type),
            'Expected a reaction to be recorded for the model, but none was.',
        );
    }

    public function assertNothingReacted(): void
    {
        Assert::assertEmpty(
            $this->operations(RecordedLike::REACT),
            'Expected no reactions to be recorded, but some were.',
        );
    }

    protected function pending(): PendingLike
    {
        return new RecordingPendingLike($this);
    }

    /** @return list<RecordedLike> */
    private function operations(string $operation): array
    {
        return array_values(array_filter(
            $this->recorded,
            static fn (RecordedLike $r): bool => $r->operation === $operation,
        ));
    }

    private function hasOperation(string $operation, Model $likeable, ?Model $actor = null, ?string $type = null): bool
    {
        foreach ($this->operations($operation) as $record) {
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
