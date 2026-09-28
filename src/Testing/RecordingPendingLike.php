<?php

declare(strict_types=1);

namespace RoundlyConsulting\Likes\Testing;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Likes\PendingLike;

/**
 * A {@see PendingLike} that delegates every write to the real action and then reports it back
 * to the {@see LikesFake}, so host-app tests can assert on what happened while the operations
 * still hit the database. A write is recorded only once it has completed: one the package
 * refuses (an unknown reaction type, no resolvable actor) throws before it is recorded, so an
 * assertion can never pass over a write that never happened.
 *
 * @internal
 */
final readonly class RecordingPendingLike extends PendingLike
{
    public function __construct(
        private LikesFake $fake,
        ?Model $actor = null,
        ?string $type = null,
    ) {
        parent::__construct($actor, $type);
    }

    public function actor(Model $actor): self
    {
        return new self($this->fake, $actor, $this->type);
    }

    public function as(string $type): self
    {
        return new self($this->fake, $this->actor, $type);
    }

    public function like(Model $likeable): bool
    {
        $result = parent::like($likeable);

        $this->fake->record(RecordedLike::LIKE, $this->resolveActor(), $likeable, $this->type);

        return $result;
    }

    public function unlike(Model $likeable): bool
    {
        $result = parent::unlike($likeable);

        $this->fake->record(RecordedLike::UNLIKE, $this->resolveActor(), $likeable, $this->type);

        return $result;
    }

    public function toggle(Model $likeable): bool
    {
        $result = parent::toggle($likeable);

        $this->fake->record($result ? RecordedLike::LIKE : RecordedLike::UNLIKE, $this->resolveActor(), $likeable, $this->type);

        return $result;
    }

    public function react(Model $likeable): bool
    {
        $result = parent::react($likeable);

        $this->fake->record(RecordedLike::REACT, $this->resolveActor(), $likeable, $this->type);

        return $result;
    }

    /**
     * @param  iterable<Model>  $likeables
     */
    public function likeMany(iterable $likeables): void
    {
        $likeables = $this->materialise($likeables);

        parent::likeMany($likeables);

        $this->each(RecordedLike::LIKE, $likeables);
    }

    /**
     * @param  iterable<Model>  $likeables
     */
    public function unlikeMany(iterable $likeables): void
    {
        $likeables = $this->materialise($likeables);

        parent::unlikeMany($likeables);

        $this->each(RecordedLike::UNLIKE, $likeables);
    }

    /**
     * Materialise the iterable so a generator is not consumed by the real action before the
     * completed writes are recorded.
     *
     * @param  iterable<Model>  $likeables
     * @return list<Model>
     */
    private function materialise(iterable $likeables): array
    {
        $list = [];

        foreach ($likeables as $likeable) {
            $list[] = $likeable;
        }

        return $list;
    }

    /**
     * Record one entry per model of a completed bulk write.
     *
     * @param  list<Model>  $likeables
     */
    private function each(string $operation, array $likeables): void
    {
        $actor = $this->resolveActor();

        foreach ($likeables as $likeable) {
            $this->fake->record($operation, $actor, $likeable, $this->type);
        }
    }
}
