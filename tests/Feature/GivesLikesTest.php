<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Likes\Events\Liked;
use RoundlyConsulting\Likes\Events\Unliked;
use RoundlyConsulting\Likes\Models\Like;
use RoundlyConsulting\Likes\Tests\Models\ActorTestModel;
use RoundlyConsulting\Likes\Tests\Models\PostTestModel;

it('toggles like for given model', function () {
    Event::fake();

    $post = PostTestModel::create();
    $actor = ActorTestModel::create();

    $this->assertDatabaseMissing('likes', [
        'actor_id' => $actor->id,
        'actor_type' => $actor->getMorphClass(),
        'likeable_id' => $post->id,
        'likeable_type' => $post->getMorphClass(),
    ]);

    $liked = $actor->toggleLike($post);

    expect($liked)->toBeTrue();

    Event::assertDispatched(function (Liked $event) use ($actor, $post) {
        return $event->actor === $actor &&
               $event->likeable === $post;
    });

    $this->assertDatabaseHas('likes', [
        'actor_id' => $actor->id,
        'actor_type' => $actor->getMorphClass(),
        'likeable_id' => $post->id,
        'likeable_type' => $post->getMorphClass(),
    ]);

    $liked = $actor->toggleLike($post);

    expect($liked)->toBeFalse();

    Event::assertDispatched(function (Unliked $event) use ($actor, $post) {
        return $event->actor === $actor &&
               $event->likeable === $post;
    });

    $this->assertSoftDeleted('likes', [
        'actor_id' => $actor->id,
        'actor_type' => $actor->getMorphClass(),
        'likeable_id' => $post->id,
        'likeable_type' => $post->getMorphClass(),
    ]);
});

it('lists of likes given by actor', function () {
    $post = PostTestModel::create();
    $actor = ActorTestModel::create();

    $actor->toggleLike($post);

    $likes = $actor->likes;

    $model = config('likes.model', Like::class);

    expect($likes)
        ->toBeInstanceOf(Collection::class)
        ->toHaveCount(1)
        ->and($likes->first())
        ->toBeInstanceOf($model);
});

it('checks whether actor has liked entity', function () {
    $post = PostTestModel::create();
    $actor = ActorTestModel::create();
    $anotherActor = ActorTestModel::create();

    $actor->toggleLike($post);

    expect($actor->hasLiked($post))
        ->toBeTrue()
        ->and($anotherActor->hasLiked($post))
        ->toBeFalse();
});
