<?php

declare(strict_types=1);

use RoundlyConsulting\Likes\Tests\Models\ActorTestModel;
use RoundlyConsulting\Likes\Tests\Models\PostTestModel;

/**
 * The ranking SQL, exercised against whatever engine the leg configured.
 *
 * TrendingScore's docblock claimed its SQL "runs on SQLite, MySQL and Postgres alike".
 * That claim had never been executed anywhere but SQLite, and it was false: a bound
 * parameter carries no type, and Postgres refuses to guess one inside an aggregate. Two
 * documented, typed, tested features died on every real Postgres install:
 *
 *  - `likes.weights` → `SUM(CASE type WHEN ? THEN ? … END)` becomes `sum(text)`:
 *    "function sum(text) does not exist".
 *  - a fractional `likes.trending.recent_multiplier` → `SUM(...) * ?` types the parameter
 *    from its neighbour and parses it as bigint: "invalid input syntax for type bigint".
 *
 * SQLite is dynamically typed and swallowed both. These cases run on every leg, so they
 * are green-but-weak on SQLite and are the real proof only on the pgsql leg — which is
 * exactly the point of running the whole suite on a real engine rather than a tagged
 * subset.
 */
beforeEach(function (): void {
    $this->postA = PostTestModel::query()->create();
    $this->postB = PostTestModel::query()->create();

    $this->actors = collect(range(1, 4))->map(fn (): ActorTestModel => ActorTestModel::query()->create());
});

it('ranks by weighted score on the configured engine', function (): void {
    config()->set('likes.reactions', ['like', 'love']);
    config()->set('likes.weights', ['like' => 1, 'love' => 4]);

    // postA: 3 likes (score 3); postB: 1 love (score 4) → postB outranks postA.
    $this->actors[0]->like($this->postA, 'like');
    $this->actors[1]->like($this->postA, 'like');
    $this->actors[2]->like($this->postA, 'like');
    $this->actors[3]->like($this->postB, 'love');

    expect(PostTestModel::query()->orderByLikeScore()->pluck('id')->all())
        ->toBe([$this->postB->getKey(), $this->postA->getKey()]);
});

/**
 * Fractional weights are what the `int|float` type on the config key promises, and they
 * are where a DECIMAL cast with too little scale would quietly round the ranking into the
 * wrong order.
 */
it('honours fractional weights on the configured engine', function (): void {
    config()->set('likes.reactions', ['like', 'love']);
    config()->set('likes.weights', ['like' => 1, 'love' => 2.5]);

    // postA: 2 likes (score 2); postB: 1 love (score 2.5) → postB edges ahead.
    $this->actors[0]->like($this->postA, 'like');
    $this->actors[1]->like($this->postA, 'like');
    $this->actors[2]->like($this->postB, 'love');

    expect(PostTestModel::query()->orderByLikeScore()->pluck('id')->all())
        ->toBe([$this->postB->getKey(), $this->postA->getKey()]);
});

/**
 * The trending expression's own bound parameter: the recency multiplier. Documented as
 * `int|float`; a fractional value is what broke.
 */
it('ranks by trending with a fractional multiplier on the configured engine', function (): void {
    config()->set('likes.trending.recent_multiplier', 2.5);

    $this->actors[0]->like($this->postA);
    $this->actors[1]->like($this->postB);
    $this->actors[2]->like($this->postB);

    expect(PostTestModel::query()->orderByTrending()->pluck('id')->all())
        ->toBe([$this->postB->getKey(), $this->postA->getKey()]);
});

/**
 * The weights + fractional multiplier combination, which is the branch of
 * portableTrending() that carries both casts at once.
 */
it('ranks by trending with weights and a fractional multiplier', function (): void {
    config()->set('likes.reactions', ['like', 'love']);
    config()->set('likes.weights', ['like' => 1, 'love' => 3.5]);
    config()->set('likes.trending.recent_multiplier', 1.5);

    $this->actors[0]->like($this->postA, 'like');
    $this->actors[1]->like($this->postB, 'love');

    expect(PostTestModel::query()->orderByTrending()->pluck('id')->all())
        ->toBe([$this->postB->getKey(), $this->postA->getKey()]);
});
