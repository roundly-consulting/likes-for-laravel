<?php

declare(strict_types=1);

namespace RoundlyConsulting\Likes\Testing;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Likes\PendingLike;

/**
 * A {@see PendingLike} that reports every performed write back to the {@see LikesFake} and then
 * delegates to the real action, so host-app tests can assert on what happened while the
 * operations still hit the database.
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
        $this->fake->record(RecordedLike::LIKE, $this->resolveActor(), $likeable, $this->type);

        return parent::like($likeable);
    }

    public function unlike(Model $likeable): bool
    {
        $this->fake->record(RecordedLike::UNLIKE, $this->resolveActor(), $likeable, $this->type);

        return parent::unlike($likeable);
    }

    public function toggle(Model $likeable): bool
    {
        $result = parent::toggle($likeable);

        $this->fake->record($result ? RecordedLike::LIKE : RecordedLike::UNLIKE, $this->resolveActor(), $likeable, $this->type);

        return $result;
    }

    public function react(Model $likeable): bool
    {
        $this->fake->record(RecordedLike::REACT, $this->resolveActor(), $likeable, $this->type);

        return parent::react($likeable);
    }

    /**
     * @param  iterable<Model>  $likeables
     */
    public function likeMany(iterable $likeables): void
    {
        $likeables = $this->each(RecordedLike::LIKE, $likeables);

        parent::likeMany($likeables);
    }

    /**
     * @param  iterable<Model>  $likeables
     */
    public function unlikeMany(iterable $likeables): void
    {
        $likeables = $this->each(RecordedLike::UNLIKE, $likeables);

        parent::unlikeMany($likeables);
    }

    /**
     * Record one entry per model. Materialises the iterable so a generator is not consumed
     * before the real action runs.
     *
     * @param  iterable<Model>  $likeables
     * @return list<Model>
     */
    private function each(string $operation, iterable $likeables): array
    {
        $actor = $this->resolveActor();
        $list = [];

        foreach ($likeables as $likeable) {
            $this->fake->record($operation, $actor, $likeable, $this->type);
            $list[] = $likeable;
        }

        return $list;
    }
}
