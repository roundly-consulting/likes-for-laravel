<?php

declare(strict_types=1);

use RoundlyConsulting\Likes\Models\Like;
use RoundlyConsulting\Likes\Tests\Models\ActorTestModel;
use RoundlyConsulting\Likes\Tests\Models\PostTestModel;

beforeEach(function (): void {
    $this->actor = ActorTestModel::create();
    $this->post = PostTestModel::create();
});

it('likes a model and reports is-liked-now', function (): void {
    expect($this->actor->like($this->post))->toBeTrue()
        ->and($this->actor->hasLiked($this->post))->toBeTrue();
});

it('like is idempotent', function (): void {
    $this->actor->like($this->post);
    $this->actor->like($this->post);

    expect(Like::query()->count())->toBe(1);
});

it('unlikes a model and reports not-liked-now', function (): void {
    $this->actor->like($this->post);

    expect($this->actor->unlike($this->post))->toBeFalse()
        ->and($this->actor->hasLiked($this->post))->toBeFalse();
});

it('unlike is idempotent', function (): void {
    expect($this->actor->unlike($this->post))->toBeFalse()
        ->and(Like::withTrashed()->count())->toBe(0);
});

it('re-like restores rather than creating a new row', function (): void {
    $this->actor->like($this->post);
    $this->actor->unlike($this->post);
    $this->actor->like($this->post);

    expect(Like::query()->count())->toBe(1)
        ->and(Like::withTrashed()->count())->toBe(1);
});

it('likes and unlikes many at once', function (): void {
    $second = PostTestModel::create();

    $this->actor->likeMany([$this->post, $second]);
    expect(Like::query()->count())->toBe(2);

    $this->actor->unlikeMany([$this->post, $second]);
    expect(Like::query()->count())->toBe(0);
});
