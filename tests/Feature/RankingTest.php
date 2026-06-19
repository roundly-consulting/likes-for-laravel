<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RoundlyConsulting\Likes\Tests\Models\ActorTestModel;
use RoundlyConsulting\Likes\Tests\Models\PostTestModel;

beforeEach(function (): void {
    config()->set('likes.reactions', ['like', 'love']);

    $this->postA = PostTestModel::query()->create();
    $this->postB = PostTestModel::query()->create();

    $this->actors = collect(range(1, 5))->map(fn (): ActorTestModel => ActorTestModel::query()->create());
});

afterEach(function (): void {
    CarbonImmutable::setTestNow();
});

it('orders by like score (default weights equal raw count)', function (): void {
    $this->actors[0]->like($this->postA);
    $this->actors[1]->like($this->postA);
    $this->actors[2]->like($this->postB);

    $ids = PostTestModel::query()->orderByLikeScore()->pluck('id')->all();

    expect($ids)->toBe([$this->postA->getKey(), $this->postB->getKey()]);
});

it('reorders results with custom weights', function (): void {
    config()->set('likes.weights', ['like' => 1, 'love' => 4]);

    // postA: 3 likes (score 3); postB: 1 love (score 4) → postB outranks postA
    $this->actors[0]->like($this->postA, 'like');
    $this->actors[1]->like($this->postA, 'like');
    $this->actors[2]->like($this->postA, 'like');
    $this->actors[3]->like($this->postB, 'love');

    $ids = PostTestModel::query()->orderByLikeScore()->pluck('id')->all();

    expect($ids)->toBe([$this->postB->getKey(), $this->postA->getKey()]);
});

it('can order by like score ascending', function (): void {
    $this->actors[0]->like($this->postA);
    $this->actors[1]->like($this->postA);
    $this->actors[2]->like($this->postB);

    $ids = PostTestModel::query()->orderByLikeScore('asc')->pluck('id')->all();

    expect($ids)->toBe([$this->postB->getKey(), $this->postA->getKey()]);
});

it('filters like score by reaction type', function (): void {
    $this->actors[0]->like($this->postA, 'like');
    $this->actors[1]->like($this->postB, 'love');

    $ids = PostTestModel::query()->orderByLikeScore('desc', 'love')->pluck('id')->all();

    expect($ids[0])->toBe($this->postB->getKey());
});

it('favours recent activity when trending', function (): void {
    // postA: 2 stale likes; postB: 1 recent like, boosted above postA.
    $now = CarbonImmutable::now();

    CarbonImmutable::setTestNow($now->subDays(30));
    $this->actors[0]->like($this->postA);
    $this->actors[1]->like($this->postA);

    CarbonImmutable::setTestNow($now);
    $this->actors[2]->like($this->postB);

    $ids = PostTestModel::query()->orderByTrending()->pluck('id')->all();

    expect($ids)->toBe([$this->postB->getKey(), $this->postA->getKey()]);
});

it('filters trending by reaction type', function (): void {
    $this->actors[0]->like($this->postA, 'like');
    $this->actors[1]->like($this->postB, 'love');

    $ids = PostTestModel::query()->orderByTrending('desc', 'love')->pluck('id')->all();

    expect($ids[0])->toBe($this->postB->getKey());
});

it('composes ranking scopes with other scopes in one query', function (): void {
    $this->actors[0]->like($this->postA);

    DB::enableQueryLog();

    $posts = PostTestModel::query()
        ->orderByLikeScore()
        ->withLikesCount()
        ->get();

    expect(count(DB::getQueryLog()))->toBe(1);
    DB::disableQueryLog();

    expect($posts)->toHaveCount(2);
});

it('excludes soft-deleted likes from ranking', function (): void {
    $this->actors[0]->like($this->postA);
    $this->actors[1]->like($this->postA);
    $this->actors[0]->unlike($this->postA);
    $this->actors[2]->like($this->postB);

    // postA now has 1 active like, postB has 1 — tie, stable order keeps A then B.
    $scores = PostTestModel::query()
        ->orderByLikeScore()
        ->withLikesCount()
        ->pluck('likes_count', 'id');

    expect((int) $scores[$this->postA->getKey()])->toBe(1)
        ->and((int) $scores[$this->postB->getKey()])->toBe(1);
});
