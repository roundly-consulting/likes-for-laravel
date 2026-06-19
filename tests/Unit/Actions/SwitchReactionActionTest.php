<?php

declare(strict_types=1);

use RoundlyConsulting\Likes\Actions\SwitchReactionAction;
use RoundlyConsulting\Likes\DataTransferObjects\LikeData;
use RoundlyConsulting\Likes\Models\Like;
use RoundlyConsulting\Likes\Tests\Models\ActorTestModel;
use RoundlyConsulting\Likes\Tests\Models\PostTestModel;

beforeEach(function (): void {
    config()->set('likes.reactions', ['like', 'love']);

    $this->action = app(SwitchReactionAction::class);
    $this->actor = ActorTestModel::query()->create();
    $this->post = PostTestModel::query()->create();
});

it('creates a reaction when none exists', function (): void {
    $result = $this->action->execute(new LikeData($this->actor, $this->post, 'love'));

    expect($result)->toBeTrue()
        ->and(Like::query()->whereNull('deleted_at')->count())->toBe(1);
});

it('keeps exactly one active row when switching', function (): void {
    $this->action->execute(new LikeData($this->actor, $this->post, 'like'));
    $this->action->execute(new LikeData($this->actor, $this->post, 'love'));

    expect(Like::query()->whereNull('deleted_at')->count())->toBe(1)
        ->and(Like::query()->first()?->type)->toBe('love');
});

it('is a no-op for the same type', function (): void {
    $this->action->execute(new LikeData($this->actor, $this->post, 'love'));
    $id = Like::query()->first()?->id;

    $this->action->execute(new LikeData($this->actor, $this->post, 'love'));

    expect(Like::query()->whereNull('deleted_at')->count())->toBe(1)
        ->and(Like::query()->first()?->id)->toBe($id);
});
