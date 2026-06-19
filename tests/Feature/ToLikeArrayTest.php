<?php

declare(strict_types=1);

use RoundlyConsulting\Likes\Tests\Models\ActorTestModel;
use RoundlyConsulting\Likes\Tests\Models\PostTestModel;

beforeEach(function (): void {
    config()->set('likes.reactions', ['like', 'love']);

    $this->post = PostTestModel::query()->create();
    $this->viewer = ActorTestModel::query()->create();
    $this->other = ActorTestModel::query()->create();
});

it('returns count, viewer_state and breakdown', function (): void {
    $this->viewer->like($this->post, 'love');
    $this->other->like($this->post, 'like');

    $array = $this->post->toLikeArray($this->viewer);

    expect($array)->toBe([
        'count' => 2,
        'viewer_state' => ['liked' => true, 'reaction' => 'love'],
        'breakdown' => ['like' => 1, 'love' => 1],
    ]);
});

it('reflects the viewer reaction', function (): void {
    $this->viewer->like($this->post, 'love');

    expect($this->post->toLikeArray($this->viewer)['viewer_state'])
        ->toBe(['liked' => true, 'reaction' => 'love']);
});

it('reports liked false for a guest', function (): void {
    $this->other->like($this->post, 'like');

    $array = $this->post->toLikeArray();

    expect($array['viewer_state'])->toBe(['liked' => false, 'reaction' => null])
        ->and($array['count'])->toBe(1);
});
