<?php

declare(strict_types=1);

use RoundlyConsulting\Likes\Tests\Models\ActorTestModel;
use RoundlyConsulting\Likes\Tests\Models\PostTestModel;

beforeEach(function (): void {
    config()->set('likes.reactions', ['like', 'love', 'wow']);

    $this->post = PostTestModel::query()->create();
    $this->a = ActorTestModel::query()->create();
    $this->b = ActorTestModel::query()->create();
    $this->c = ActorTestModel::query()->create();
});

it('returns per-type counts and total', function (): void {
    $this->a->like($this->post, 'like');
    $this->b->like($this->post, 'love');
    $this->c->like($this->post, 'love');

    $summary = $this->post->reactionSummary();

    expect($summary->counts)->toBe(['like' => 1, 'love' => 2])
        ->and($summary->total)->toBe(3);
});

it('reports the top reaction type', function (): void {
    $this->a->like($this->post, 'love');
    $this->b->like($this->post, 'love');
    $this->c->like($this->post, 'like');

    expect($this->post->reactionSummary()->top)->toBe('love');
});

it('breaks ties using config reaction order', function (): void {
    $this->a->like($this->post, 'love');
    $this->b->like($this->post, 'like');

    // like and love both have 1; "like" comes first in config order.
    expect($this->post->reactionSummary()->top)->toBe('like');
});

it('returns null top and zero total when empty', function (): void {
    $summary = $this->post->reactionSummary();

    expect($summary->top)->toBeNull()
        ->and($summary->total)->toBe(0)
        ->and($summary->counts)->toBe([]);
});

it('includes the viewer current reaction', function (): void {
    $this->a->like($this->post, 'wow');

    expect($this->post->reactionSummary($this->a)->viewerReaction)->toBe('wow');
});

it('has a null viewer reaction without a viewer', function (): void {
    $this->a->like($this->post, 'wow');

    expect($this->post->reactionSummary()->viewerReaction)->toBeNull();
});

it('falls back to a present type when none are configured', function (): void {
    $this->a->like($this->post, 'wow');

    // Narrow the allowlist so the stored "wow" is no longer a configured type.
    config()->set('likes.reactions', ['like']);

    expect($this->post->reactionSummary()->top)->toBe('wow');
});

it('excludes soft-deleted likes', function (): void {
    $this->a->like($this->post, 'like');
    $this->b->like($this->post, 'love');
    $this->b->unlike($this->post, 'love');

    $summary = $this->post->reactionSummary();

    expect($summary->counts)->toBe(['like' => 1])
        ->and($summary->total)->toBe(1);
});
