<?php

declare(strict_types=1);

use RoundlyConsulting\Likes\Testing\InteractsWithLikes;
use RoundlyConsulting\Likes\Tests\Models\ActorTestModel;
use RoundlyConsulting\Likes\Tests\Models\PostTestModel;

uses(InteractsWithLikes::class);

beforeEach(function (): void {
    config()->set('likes.reactions', ['like', 'love']);

    $this->actor = ActorTestModel::query()->create();
    $this->post = PostTestModel::query()->create();
});

it('likes, toggles and unlikes via the remembered actor', function (): void {
    $this->actingAsLiker($this->actor);

    expect($this->likeAs($this->post))->toBeTrue()
        ->and($this->post->isLikedBy($this->actor))->toBeTrue();

    expect($this->unlikeAs($this->post))->toBeFalse()
        ->and($this->post->isLikedBy($this->actor))->toBeFalse();

    expect($this->toggleAs($this->post))->toBeTrue();
});

it('accepts an explicit actor', function (): void {
    $this->likeAs($this->post, 'love', $this->actor);

    expect($this->post->isLikedBy($this->actor, 'love'))->toBeTrue();
});

it('throws without an actor', function (): void {
    $this->likeAs($this->post);
})->throws(RuntimeException::class);

it('passes the toBeLikedBy matcher', function (): void {
    $this->actor->like($this->post, 'like');
    $this->actor->like($this->post, 'love');

    expect($this->post)->toBeLikedBy($this->actor)
        ->and($this->post)->toBeLikedBy($this->actor, 'love');
});

it('passes the toHaveReaction matcher', function (): void {
    $this->actor->like($this->post, 'love');

    expect($this->post)->toHaveReaction('love');
});
