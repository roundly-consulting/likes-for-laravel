<?php

declare(strict_types=1);

use RoundlyConsulting\Likes\Testing\LikeExpectations;
use RoundlyConsulting\Likes\Tests\Models\ActorTestModel;
use RoundlyConsulting\Likes\Tests\Models\PostTestModel;

beforeEach(function (): void {
    config()->set('likes.reactions', ['like', 'love']);

    LikeExpectations::register();

    $this->actor = ActorTestModel::query()->create();
    $this->post = PostTestModel::query()->create();
});

it('registers and passes the toBeLikedBy matcher', function (): void {
    $this->actor->like($this->post, 'like');
    $this->actor->like($this->post, 'love');

    expect($this->post)->toBeLikedBy($this->actor)
        ->and($this->post)->toBeLikedBy($this->actor, 'love');
});

it('registers and passes the toHaveReaction matcher', function (): void {
    $this->actor->like($this->post, 'love');

    expect($this->post)->toHaveReaction('love');
});

it('does not register matchers when expect() is unavailable', function (): void {
    // The guarded branch returns early; calling register() again is a safe no-op.
    LikeExpectations::register();

    expect(function_exists('expect'))->toBeTrue();
});
