<?php

declare(strict_types=1);

use RoundlyConsulting\Likes\Exceptions\InvalidReactionTypeException;
use RoundlyConsulting\Likes\Models\Like;
use RoundlyConsulting\Likes\Tests\Models\ActorTestModel;
use RoundlyConsulting\Likes\Tests\Models\PostTestModel;

beforeEach(function (): void {
    config()->set('likes.reactions', ['like', 'love', 'wow']);

    $this->actor = ActorTestModel::create();
    $this->post = PostTestModel::create();
});

it('persists a typed reaction', function (): void {
    $this->actor->like($this->post, 'love');

    expect(Like::query()->where('type', 'love')->count())->toBe(1);
});

it('keeps separate rows per reaction type', function (): void {
    $this->actor->like($this->post, 'love');
    $this->actor->like($this->post, 'wow');

    expect(Like::query()->count())->toBe(2);
});

it('rejects an unconfigured reaction type', function (): void {
    $this->actor->like($this->post, 'angry');
})->throws(InvalidReactionTypeException::class);

it('checks like state per reaction type', function (): void {
    $this->actor->like($this->post, 'love');

    expect($this->post->isLikedBy($this->actor, 'love'))->toBeTrue()
        ->and($this->post->isLikedBy($this->actor, 'wow'))->toBeFalse();
});

it('counts likes scoped per reaction type', function (): void {
    $this->actor->like($this->post, 'love');
    ActorTestModel::create()->like($this->post, 'wow');

    expect($this->post->likesCount('love'))->toBe(1)
        ->and($this->post->likesCount('wow'))->toBe(1)
        ->and($this->post->likesCount())->toBe(2);
});

it('orders by likes filtered by reaction type', function (): void {
    $other = PostTestModel::create();

    $this->actor->like($this->post, 'love');
    ActorTestModel::create()->like($this->post, 'wow');
    $this->actor->like($other, 'wow');

    $ranked = PostTestModel::orderByLikesDesc('love')->get();

    expect($ranked->first()->id)->toBe($this->post->id);
});
