<?php

declare(strict_types=1);

namespace RoundlyConsulting\Likes\Testing;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Likes\Support\ReactionType;

/**
 * A single recorded like operation captured by {@see LikesFake}.
 */
final readonly class RecordedLike
{
    public const string LIKE = 'like';

    public const string UNLIKE = 'unlike';

    public const string REACT = 'react';

    public function __construct(
        public string $operation,
        public Model $actor,
        public Model $likeable,
        public ?string $type,
    ) {}

    /**
     * Same likeable and, when a type is asked for, the same reaction type — a recorded `null`
     * type means the configured default, as it does for the write itself.
     */
    public function matches(Model $likeable, ?string $type = null): bool
    {
        if (! $this->sameModel($this->likeable, $likeable)) {
            return false;
        }

        return $type === null || ($this->type ?? ReactionType::default()) === $type;
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
