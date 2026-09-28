<?php

declare(strict_types=1);

use RoundlyConsulting\Likes\Facades\Likes;
use RoundlyConsulting\Likes\Tests\Models\ActorTestModel;
use RoundlyConsulting\Likes\Tests\Models\PostTestModel;

/**
 * With several active reactions the viewer's reaction came from a `limit 1` / `value()` with
 * no ORDER BY — whatever row the engine returned first. It is now the viewer's reaction that
 * comes first in `likes.reactions` (the same rule that breaks `top` ties). The config order
 * here deliberately differs from both insertion and alphabetical order.
 */
beforeEach(function (): void {
    config()->set('likes.reactions', ['like', 'wow', 'love']);

    $this->viewer = ActorTestModel::query()->create();
    $this->post = PostTestModel::query()->create();

    $this->viewer->like($this->post, 'love');
    $this->viewer->like($this->post, 'wow');
});

it('picks the first configured reaction for liked_reaction', function (): void {
    $row = PostTestModel::query()->withLikedState($this->viewer)->findOrFail($this->post->getKey());

    expect($row->liked_reaction)->toBe('wow');
});

it('picks the same reaction for summary()->viewerReaction and toLikeArray()', function (): void {
    expect(Likes::for($this->post)->summary($this->viewer)->viewerReaction)->toBe('wow')
        ->and($this->post->toLikeArray($this->viewer)['viewer_state']['reaction'])->toBe('wow');
});

it('follows the config order when it changes', function (): void {
    config()->set('likes.reactions', ['like', 'love', 'wow']);

    $row = PostTestModel::query()->withLikedState($this->viewer)->findOrFail($this->post->getKey());

    expect($row->liked_reaction)->toBe('love')
        ->and($this->post->reactionSummary($this->viewer)->viewerReaction)->toBe('love');
});

it('ranks reactions missing from the config after configured ones', function (): void {
    config()->set('likes.reactions', ['like', 'love']);

    expect($this->post->reactionSummary($this->viewer)->viewerReaction)->toBe('love')
        ->and(PostTestModel::query()->withLikedState($this->viewer)->first()?->liked_reaction)->toBe('love');
});

it('flags is_liked as 1, not the number of reactions', function (): void {
    $this->viewer->like($this->post, 'like');

    $row = PostTestModel::query()->withLikedState($this->viewer)->findOrFail($this->post->getKey());

    expect((int) $row->is_liked)->toBe(1);
});
