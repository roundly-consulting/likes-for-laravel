<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use RoundlyConsulting\Likes\Facades\Likes;
use RoundlyConsulting\Likes\Tests\Models\ActorTestModel;
use RoundlyConsulting\Likes\Tests\Models\PostTestModel;

/**
 * A typed eager count used to land in the same `likes_count` attribute as the untyped one,
 * and `likesCount()` / `Likes::for()->count()` then reported it as the all-reactions total:
 * a row from `orderByLikesDesc('love')` claimed 1 like while it had 3.
 */
beforeEach(function (): void {
    config()->set('likes.reactions', ['like', 'love', 'wow']);

    $this->actor = ActorTestModel::query()->create();
    $this->post = PostTestModel::query()->create();

    $this->actor->like($this->post, 'like');
    $this->actor->like($this->post, 'love');
    $this->actor->like($this->post, 'wow');
});

it('keeps a typed eager count out of the untyped total', function (): void {
    $row = PostTestModel::query()->orderByLikesDesc('love')->findOrFail($this->post->getKey());

    expect($row->likesCount())->toBe(3)
        ->and(Likes::for($row)->count())->toBe(3)
        ->and($row->likesCount('love'))->toBe(1)
        ->and($row->getAttribute('likes_count'))->toBeNull();
});

it('hydrates a typed count under its own likes_{type}_count attribute', function (): void {
    $row = PostTestModel::query()->withLikesCount('love')->findOrFail($this->post->getKey());

    expect((int) $row->getAttribute('likes_love_count'))->toBe(1);
});

it('reads a typed eager count without a query', function (): void {
    $row = PostTestModel::query()->withLikesCount('love')->findOrFail($this->post->getKey());
    $row->setAttribute('likes_love_count', 9);

    DB::enableQueryLog();

    expect($row->likesCount('love'))->toBe(9)
        ->and(DB::getQueryLog())->toBe([]);
});

it('orders by the typed count in both directions', function (): void {
    $other = PostTestModel::query()->create();
    ActorTestModel::query()->create()->like($other, 'love');
    ActorTestModel::query()->create()->like($other, 'love');

    expect(PostTestModel::query()->orderByLikesDesc('love')->pluck('id')->all())
        ->toBe([$other->getKey(), $this->post->getKey()])
        ->and(PostTestModel::query()->orderByLikes('love')->pluck('id')->all())
        ->toBe([$this->post->getKey(), $other->getKey()]);
});

it('composes an untyped and a typed count on one row', function (): void {
    $row = PostTestModel::query()
        ->withLikesCount()
        ->withLikesCount('wow')
        ->findOrFail($this->post->getKey());

    expect((int) $row->getAttribute('likes_count'))->toBe(3)
        ->and((int) $row->getAttribute('likes_wow_count'))->toBe(1)
        ->and($row->likesCount())->toBe(3)
        ->and($row->likesCount('wow'))->toBe(1);
});

it('counts live on a strict-mode model with no eager count loaded', function (): void {
    // Model::shouldBeStrict() makes reading an unloaded attribute throw; the eager-count
    // probe must not read one.
    Model::preventAccessingMissingAttributes();

    try {
        $row = PostTestModel::query()->findOrFail($this->post->getKey());

        expect($row->likesCount())->toBe(3)
            ->and($row->likesCount('love'))->toBe(1);
    } finally {
        Model::preventAccessingMissingAttributes(false);
    }
});
