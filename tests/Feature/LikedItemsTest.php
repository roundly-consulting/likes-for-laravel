<?php

declare(strict_types=1);

use RoundlyConsulting\Likes\Tests\Models\ActorTestModel;
use RoundlyConsulting\Likes\Tests\Models\PostTestModel;

beforeEach(function (): void {
    config()->set('likes.reactions', ['like', 'love']);

    $this->actor = ActorTestModel::query()->create();
    $this->other = ActorTestModel::query()->create();

    $this->postA = PostTestModel::query()->create();
    $this->postB = PostTestModel::query()->create();
    $this->postC = PostTestModel::query()->create();
});

it('returns the models the actor liked', function (): void {
    $this->actor->like($this->postA, 'like');
    $this->actor->like($this->postB, 'love');

    $ids = $this->actor->likedItems(PostTestModel::class)->orderBy('posts.id')->pluck('posts.id')->all();

    expect($ids)->toBe([$this->postA->getKey(), $this->postB->getKey()]);
});

it('filters liked items by reaction type', function (): void {
    $this->actor->like($this->postA, 'like');
    $this->actor->like($this->postB, 'love');

    $ids = $this->actor->likedItems(PostTestModel::class, 'love')->pluck('posts.id')->all();

    expect($ids)->toBe([$this->postB->getKey()]);
});

it('excludes soft-deleted likes and restores on re-like', function (): void {
    $this->actor->like($this->postA, 'like');
    $this->actor->unlike($this->postA, 'like');

    expect($this->actor->likedItems(PostTestModel::class)->count())->toBe(0);

    $this->actor->like($this->postA, 'like');

    expect($this->actor->likedItems(PostTestModel::class)->count())->toBe(1);
});

it('composes with additional constraints and eager loading', function (): void {
    $this->actor->like($this->postA, 'like');
    $this->actor->like($this->postB, 'like');

    $items = $this->actor->likedItems(PostTestModel::class)
        ->where('posts.id', $this->postB->getKey())
        ->get();

    expect($items)->toHaveCount(1)
        ->and($items->first()?->getKey())->toBe($this->postB->getKey());
});

it('does not leak across actor instances', function (): void {
    $this->actor->like($this->postA, 'like');
    $this->other->like($this->postB, 'like');

    $ids = $this->actor->likedItems(PostTestModel::class)->pluck('posts.id')->all();

    expect($ids)->toBe([$this->postA->getKey()]);
});
