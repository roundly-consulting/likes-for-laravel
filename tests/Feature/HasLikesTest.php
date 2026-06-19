<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Collection;
use RoundlyConsulting\Likes\Models\Like;
use RoundlyConsulting\Likes\Tests\Models\ActorTestModel;
use RoundlyConsulting\Likes\Tests\Models\PostTestModel;

it('lists of likes for given entity', function () {
    $post = PostTestModel::create();
    $actor = ActorTestModel::create();

    $actor->toggleLike($post);

    $likes = $post->likes;

    $model = config('likes.model', Like::class);

    expect($likes)
        ->toBeInstanceOf(Collection::class)
        ->toHaveCount(1)
        ->and($likes->first())
        ->toBeInstanceOf($model)
        ->actor->toBeInstanceOf(ActorTestModel::class)
        ->actor->id->toBe($actor->id);
});

it('checks whether entity has been liked by actor', function () {
    $post = PostTestModel::create();
    $actor = ActorTestModel::create();
    $anotherActor = ActorTestModel::create();

    $actor->toggleLike($post);

    expect($post->hasBeenLikedBy($actor))
        ->toBeTrue()
        ->and($post->hasBeenLikedBy($anotherActor))
        ->toBeFalse();
});
