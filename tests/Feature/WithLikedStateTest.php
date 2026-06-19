<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use RoundlyConsulting\Likes\Tests\Models\ActorTestModel;
use RoundlyConsulting\Likes\Tests\Models\PostTestModel;

beforeEach(function (): void {
    config()->set('likes.reactions', ['like', 'love']);

    $this->actor = ActorTestModel::query()->create();
    $this->liked = PostTestModel::query()->create();
    $this->unliked = PostTestModel::query()->create();

    $this->actor->like($this->liked, 'love');
});

it('hydrates is_liked per row in a single query', function (): void {
    DB::enableQueryLog();

    $posts = PostTestModel::query()->withLikedState($this->actor)->orderBy('id')->get();

    expect(count(DB::getQueryLog()))->toBe(1);
    DB::disableQueryLog();

    expect((bool) $posts[0]->is_liked)->toBeTrue()
        ->and((bool) $posts[1]->is_liked)->toBeFalse();
});

it('hydrates liked_reaction with the viewer reaction type', function (): void {
    $posts = PostTestModel::query()->withLikedState($this->actor)->orderBy('id')->get();

    expect($posts[0]->liked_reaction)->toBe('love')
        ->and($posts[1]->liked_reaction)->toBeNull();
});

it('defaults to the authenticated actor', function (): void {
    $this->actingAs($this->actor);

    $post = PostTestModel::query()->withLikedState()->whereKey($this->liked->getKey())->first();

    expect((bool) $post->is_liked)->toBeTrue()
        ->and($post->liked_reaction)->toBe('love');
});

it('composes with withLikesCount in one query', function (): void {
    DB::enableQueryLog();

    $post = PostTestModel::query()
        ->withLikedState($this->actor)
        ->withLikesCount()
        ->whereKey($this->liked->getKey())
        ->first();

    expect(count(DB::getQueryLog()))->toBe(1);
    DB::disableQueryLog();

    expect((bool) $post->is_liked)->toBeTrue()
        ->and((int) $post->likes_count)->toBe(1);
});

it('filters liked state by reaction type', function (): void {
    $post = PostTestModel::query()
        ->withLikedState($this->actor, 'like')
        ->whereKey($this->liked->getKey())
        ->first();

    expect((bool) $post->is_liked)->toBeFalse()
        ->and($post->liked_reaction)->toBeNull();
});

it('renders false and null for guests', function (): void {
    $post = PostTestModel::query()->withLikedState()->whereKey($this->liked->getKey())->first();

    expect((bool) $post->is_liked)->toBeFalse()
        ->and($post->liked_reaction)->toBeNull();
});

it('ignores soft-deleted likes', function (): void {
    $this->actor->unlike($this->liked, 'love');

    $post = PostTestModel::query()->withLikedState($this->actor)->whereKey($this->liked->getKey())->first();

    expect((bool) $post->is_liked)->toBeFalse()
        ->and($post->liked_reaction)->toBeNull();
});

it('preserves explicit selects alongside the state columns', function (): void {
    $post = PostTestModel::query()
        ->select('posts.id')
        ->withLikedState($this->actor)
        ->whereKey($this->liked->getKey())
        ->first();

    expect($post->id)->toBe($this->liked->getKey())
        ->and((bool) $post->is_liked)->toBeTrue();
});
