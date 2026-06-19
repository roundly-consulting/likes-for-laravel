<?php

declare(strict_types=1);

use RoundlyConsulting\Likes\Models\Like;
use RoundlyConsulting\Likes\Tests\Models\ActorTestModel;
use RoundlyConsulting\Likes\Tests\Models\PostTestModel;

it('applies the forActor state', function (): void {
    $actor = ActorTestModel::create();

    $like = Like::factory()->forActor($actor)->create();

    expect($like->actor_id)->toBe($actor->getKey())
        ->and($like->actor_type)->toBe($actor->getMorphClass());
});

it('applies the forLikeable state', function (): void {
    $post = PostTestModel::create();

    $like = Like::factory()->forLikeable($post)->create();

    expect($like->likeable_id)->toBe($post->getKey())
        ->and($like->likeable_type)->toBe($post->getMorphClass());
});

it('applies the ofType state', function (): void {
    $like = Like::factory()->ofType('love')->create();

    expect($like->type)->toBe('love');
});

it('resolves morphs when built from actor and likeable states', function (): void {
    $actor = ActorTestModel::create();
    $post = PostTestModel::create();

    $like = Like::factory()->forActor($actor)->forLikeable($post)->create();

    expect($like->actor)->toBeInstanceOf(ActorTestModel::class)
        ->and($like->likeable)->toBeInstanceOf(PostTestModel::class);
});
