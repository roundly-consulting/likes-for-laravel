<?php

declare(strict_types=1);

namespace RoundlyConsulting\Likes\Testing;

use Illuminate\Database\Eloquent\Model;

/**
 * A single recorded like operation captured by {@see LikesFake}.
 */
final readonly class RecordedLike
{
    public function __construct(
        public string $operation,
        public Model $actor,
        public Model $likeable,
        public ?string $type,
    ) {}

    public function matches(Model $likeable, ?string $type = null): bool
    {
        if (! $this->sameModel($this->likeable, $likeable)) {
            return false;
        }

        return $type === null || $this->type === $type;
    }

    public function byActor(Model $actor): bool
    {
        return $this->sameModel($this->actor, $actor);
    }

    private function sameModel(Model $a, Model $b): bool
    {
        return $a->getMorphClass() === $b->getMorphClass()
            && $a->getKey() === $b->getKey();
    }
}
