<?php

declare(strict_types=1);

namespace RoundlyConsulting\Likes\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
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
        ];
    }
}
