<?php

declare(strict_types=1);

use RoundlyConsulting\Likes\Models\Like;
use RoundlyConsulting\Likes\Tests\Models\ActorTestModel;
use RoundlyConsulting\Likes\Tests\Models\PostTestModel;

it('builds a like from its factory', function (): void {
    $like = Like::factory()->create();

    expect($like)
        ->toBeInstanceOf(Like::class)
        ->and($like->exists)->toBeTrue();
});

it('soft deletes a like', function (): void {
    $like = Like::factory()->create();

    $like->delete();

    expect(Like::query()->count())->toBe(0)
        ->and(Like::withTrashed()->count())->toBe(1);
});

it('resolves the actor and likeable morph relations', function (): void {
    $post = PostTestModel::create();
    $actor = ActorTestModel::create();

    $actor->toggleLike($post);

    $like = Like::query()->firstOrFail();

    expect($like->actor)->toBeInstanceOf(ActorTestModel::class)
        ->and($like->likeable)->toBeInstanceOf(PostTestModel::class);
});
