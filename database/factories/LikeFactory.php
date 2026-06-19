<?php

declare(strict_types=1);

namespace RoundlyConsulting\Likes\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Likes\Models\Like;

/** @extends Factory<Like> */
final class LikeFactory extends Factory
{
    protected $model = Like::class;

    /** @return array<model-property<Like>, mixed> */
    public function definition(): array
    {
        return [
            'actor_id' => $this->faker->randomNumber(),
            'actor_type' => 'actor',
            'likeable_id' => $this->faker->randomNumber(),
            'likeable_type' => 'likeable',
            'type' => 'like',
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
}
