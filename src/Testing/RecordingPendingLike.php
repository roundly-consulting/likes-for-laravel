<?php

declare(strict_types=1);

namespace RoundlyConsulting\Likes\Testing;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Likes\PendingLike;

/**
 * A {@see PendingLike} that reports every performed operation back to the
 * {@see LikesFake} before delegating to the real action, so host-app tests can
 * assert on what happened while the operations still hit the database.
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
        $this->fake->record('like', $this->resolveActor(), $likeable, $this->type);

        return parent::like($likeable);
    }

    public function unlike(Model $likeable): bool
    {
        $this->fake->record('unlike', $this->resolveActor(), $likeable, $this->type);

        return parent::unlike($likeable);
    }

    public function toggle(Model $likeable): bool
    {
        $result = parent::toggle($likeable);

        $this->fake->record($result ? 'like' : 'unlike', $this->resolveActor(), $likeable, $this->type);

        return $result;
    }

    public function react(Model $likeable): bool
    {
        $this->fake->record('like', $this->resolveActor(), $likeable, $this->type);

        return parent::react($likeable);
    }
}
