<?php

declare(strict_types=1);

namespace RoundlyConsulting\Likes\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use RoundlyConsulting\Likes\Models\Like;
use RoundlyConsulting\Likes\Support\LikeModel;
use RoundlyConsulting\Likes\Support\ReactionType;
use RoundlyConsulting\PackageToolkit\Enums\KeyType;

/** @extends Factory<Like> */
final class LikeFactory extends Factory
{
    /** @var class-string<Like> */
    protected $model = Like::class;

    /**
     * Build the model the host configured in `likes.model`, not the packaged one, so a host
     * subclass comes out of the factory with its own events and observers.
     *
     * @return class-string<Like>
     */
    public function modelName(): string
    {
        return LikeModel::class();
    }

    /** @return array<model-property<Like>, mixed> */
    public function definition(): array
    {
        return [
            'actor_id' => $this->morphId(),
            'actor_type' => 'actor',
            'likeable_id' => $this->morphId(),
            'likeable_type' => 'likeable',
            'type' => ReactionType::default(),
        ];
    }

    public function forActor(Model $actor): self
    {
        return $this->state([
            'actor_id' => $actor->getKey(),
            'actor_type' => $actor->getMorphClass(),
        ]);
    }

    public function forLikeable(Model $likeable): self
    {
        return $this->state([
            'likeable_id' => $likeable->getKey(),
            'likeable_type' => $likeable->getMorphClass(),
        ]);
    }

    public function ofType(string $type): self
    {
        return $this->state(['type' => $type]);
    }

    /**
     * A morph id of the type the migration built from `likes.key_type`: Postgres refuses an
     * integer in a uuid column.
     */
    private function morphId(): int|string
    {
        return match (KeyType::fromConfig('likes.key_type')) {
            KeyType::Uuid => (string) Str::uuid(),
            KeyType::Ulid => strtolower((string) Str::ulid()),
            KeyType::BigInt => $this->faker->randomNumber(),
        };
    }
}
