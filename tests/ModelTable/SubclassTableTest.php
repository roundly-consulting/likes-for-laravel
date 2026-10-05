<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use RoundlyConsulting\Likes\Tests\Fixtures\TableOverrideLikeTestCase;
use RoundlyConsulting\Likes\Tests\Models\ActorTestModel;
use RoundlyConsulting\Likes\Tests\Models\PostTestModel;

/**
 * Writes, relations and `Likes::for()` go through the swapped model and so land in its
 * `reactions` table; the scopes that read the likes table directly used `likes.table` instead
 * and found nothing (or, without a `likes` table, failed outright).
 *
 * Runs on {@see TableOverrideLikeTestCase}, which this directory is bound to.
 */
beforeEach(function (): void {
    $this->actor = ActorTestModel::query()->create();
    $this->post = PostTestModel::query()->create();

    $this->actor->like($this->post);
});

it('writes into the swapped model table', function (): void {
    expect(DB::table('reactions')->count())->toBe(1)
        ->and(DB::table('likes')->count())->toBe(0);
});

it('hydrates the liked state from the swapped model table', function (): void {
    $post = PostTestModel::query()->withLikedState($this->actor)->sole();

    expect((int) $post->getAttribute('is_liked'))->toBe(1)
        ->and($post->getAttribute('liked_reaction'))->toBe('like');
});

it('ranks by score and trending from the swapped model table', function (): void {
    $scored = PostTestModel::query()->orderByLikeScore()->sole();
    $trending = PostTestModel::query()->orderByTrending()->sole();

    expect((float) $scored->getAttribute('like_score'))->toBe(1.0)
        ->and((float) $trending->getAttribute('trending_score'))->toBeGreaterThan(0.0);
});

it('lists liked items from the swapped model table', function (): void {
    expect($this->actor->likesOf(PostTestModel::class)->pluck('posts.id')->all())->toBe([$this->post->getKey()])
        ->and($this->actor->likedItems(PostTestModel::class)->pluck('posts.id')->all())->toBe([$this->post->getKey()]);
});
