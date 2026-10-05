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

/**
 * The relation joins the likes table, so an item the actor reacted to with several types
 * came back once per reaction — and `paginate()->total()` counted every copy.
 */
it('returns each liked item once however many reactions it has', function (): void {
    $this->actor->like($this->postA, 'like');
    $this->actor->like($this->postA, 'love');
    $this->actor->like($this->postB, 'love');

    $ids = $this->actor->likedItems(PostTestModel::class)->orderBy('posts.id')->pluck('posts.id')->all();
    $page = $this->actor->likedItems(PostTestModel::class)->paginate(10);

    expect($ids)->toBe([$this->postA->getKey(), $this->postB->getKey()])
        ->and($page->total())->toBe(2)
        ->and($page->items())->toHaveCount(2)
        ->and($this->actor->likedItems(PostTestModel::class)->count())->toBe(2)
        ->and($this->actor->likesOf(PostTestModel::class)->pluck('posts.id')->sort()->values()->all())
        ->toBe([$this->postA->getKey(), $this->postB->getKey()]);
});

it('keeps one row per item while one of its reactions is removed', function (): void {
    $this->actor->like($this->postA, 'like');
    $this->actor->like($this->postA, 'love');
    $this->actor->unlike($this->postA, 'like');

    $items = $this->actor->likedItems(PostTestModel::class)->get();

    expect($items)->toHaveCount(1)
        ->and($items->first()?->getRelation('pivot')->getAttribute('type'))->toBe('love');
});

it('still filters a multi-reaction item by one type', function (): void {
    $this->actor->like($this->postA, 'like');
    $this->actor->like($this->postA, 'love');

    $items = $this->actor->likedItems(PostTestModel::class, 'love')->get();

    expect($items)->toHaveCount(1)
        ->and($items->first()?->getRelation('pivot')->getAttribute('type'))->toBe('love');
});

/**
 * `wherePivot()` always hands where() four arguments, so a two-argument call read the type as
 * the operator — and `like` IS an SQL operator. With the package's default reaction the
 * documented typed form threw "Illegal operator and value combination".
 */
it('filters liked items by the default like reaction', function (): void {
    $this->actor->like($this->postA, 'like');
    $this->actor->like($this->postB, 'love');

    $ids = $this->actor->likedItems(PostTestModel::class, 'like')->pluck('posts.id')->all();

    expect($ids)->toBe([$this->postA->getKey()]);
});
