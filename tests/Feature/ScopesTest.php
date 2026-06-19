<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use RoundlyConsulting\Likes\Tests\Models\ActorTestModel;
use RoundlyConsulting\Likes\Tests\Models\PostTestModel;

beforeEach(function (): void {
    $this->popular = PostTestModel::create();
    $this->quiet = PostTestModel::create();
    $this->ignored = PostTestModel::create();

    $this->a = ActorTestModel::create();
    $this->b = ActorTestModel::create();

    $this->a->like($this->popular);
    $this->b->like($this->popular);
    $this->a->like($this->quiet);
});

it('orders by likes descending', function (): void {
    $ranked = PostTestModel::orderByLikesDesc()->get();

    expect($ranked->first()->id)->toBe($this->popular->id)
        ->and($ranked->last()->id)->toBe($this->ignored->id);
});

it('orders by likes ascending', function (): void {
    $ranked = PostTestModel::orderByLikes()->get();

    expect($ranked->first()->id)->toBe($this->ignored->id)
        ->and($ranked->last()->id)->toBe($this->popular->id);
});

it('filters models liked by an actor', function (): void {
    $liked = PostTestModel::whereLikedBy($this->a)->pluck('id');

    expect($liked)->toContain($this->popular->id, $this->quiet->id)
        ->not->toContain($this->ignored->id);
});

it('filters models not liked by an actor', function (): void {
    $notLiked = PostTestModel::whereNotLikedBy($this->a)->pluck('id');

    expect($notLiked)->toContain($this->ignored->id)
        ->not->toContain($this->popular->id);
});

it('composes scopes with other where clauses', function (): void {
    $result = PostTestModel::whereLikedBy($this->a)
        ->where('id', $this->popular->id)
        ->get();

    expect($result)->toHaveCount(1)
        ->and($result->first()->id)->toBe($this->popular->id);
});

it('hydrates a likes_count attribute via withLikesCount', function (): void {
    $post = PostTestModel::withLikesCount()->find($this->popular->id);

    expect($post->getAttribute('likes_count'))->toBe(2);
});

it('reads the eager-loaded count without an extra query', function (): void {
    $post = PostTestModel::withLikesCount()->find($this->popular->id);

    DB::enableQueryLog();
    $count = $post->likesCount();
    DB::disableQueryLog();

    expect($count)->toBe(2)
        ->and(DB::getQueryLog())->toHaveCount(0);
});

it('counts likes live when not eager loaded', function (): void {
    $post = PostTestModel::find($this->popular->id);

    expect($post->likesCount())->toBe(2);
});
