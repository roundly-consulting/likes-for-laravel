<?php

declare(strict_types=1);

namespace RoundlyConsulting\Likes;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Likes\Actions\LikeAction;
use RoundlyConsulting\Likes\Actions\LikeManyAction;
use RoundlyConsulting\Likes\Actions\ToggleLikeAction;
use RoundlyConsulting\Likes\Actions\UnlikeAction;
use RoundlyConsulting\Likes\Actions\UnlikeManyAction;
use RoundlyConsulting\Likes\DataTransferObjects\LikeData;
use RoundlyConsulting\Likes\Exceptions\NoAuthenticatedActorException;
use RoundlyConsulting\Likes\Models\Like;

/**
 * Immutable, fluent builder produced by LikeManager::actor()/as().
 */
final readonly class PendingLike
{
    public function __construct(
        private ?Model $actor = null,
        private ?string $type = null,
    ) {}

    public function actor(Model $actor): self
    {
        return new self($actor, $this->type);
    }

    public function as(string $type): self
    {
        return new self($this->actor, $type);
    }

    public function like(Model $likeable): bool
    {
        return app(LikeAction::class)->execute($this->data($likeable));
    }

    public function unlike(Model $likeable): bool
    {
        return app(UnlikeAction::class)->execute($this->data($likeable));
    }

    public function toggle(Model $likeable): bool
    {
        return app(ToggleLikeAction::class)->execute($this->data($likeable));
    }

    public function has(Model $likeable): bool
    {
        $data = $this->data($likeable);

        /** @var class-string<Like> $model */
        $model = config('likes.model', Like::class);

        return $model::query()
            ->whereMorphedTo('actor', $data->actor)
            ->whereMorphedTo('likeable', $data->likeable)
            ->where('type', $data->type)
            ->exists();
    }

    /**
     * @param  iterable<Model>  $likeables
     */
    public function likeMany(iterable $likeables): void
    {
        app(LikeManyAction::class)->execute($this->resolveActor(), $likeables, $this->type);
    }

    /**
     * @param  iterable<Model>  $likeables
     */
    public function unlikeMany(iterable $likeables): void
    {
        app(UnlikeManyAction::class)->execute($this->resolveActor(), $likeables, $this->type);
    }

    private function data(Model $likeable): LikeData
    {
        return new LikeData($this->resolveActor(), $likeable, $this->type);
    }

    private function resolveActor(): Model
    {
        if ($this->actor instanceof Model) {
            return $this->actor;
        }

        $resolved = $this->resolveFromConfig();

        if (! $resolved instanceof Model) {
            throw NoAuthenticatedActorException::make();
        }

        return $resolved;
    }

    private function resolveFromConfig(): ?Model
    {
        $resolver = config('likes.actor_resolver');

        if ($resolver === null) {
            $user = auth()->user();

            return $user instanceof Model ? $user : null;
        }

        if (is_string($resolver) && class_exists($resolver)) {
            /** @var callable $resolver */
            $resolver = app($resolver);
        }

        if (is_callable($resolver)) {
            $result = $resolver();

            return $result instanceof Model ? $result : null;
        }

        return null;
    }
}
