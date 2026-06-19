<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Likes\Actions\LikeAction;
use RoundlyConsulting\Likes\DataTransferObjects\LikeData;
use RoundlyConsulting\Likes\Events\Liked;
use RoundlyConsulting\Likes\Events\LikeToggled;
use RoundlyConsulting\Likes\Models\Like;
use RoundlyConsulting\Likes\Tests\Models\ActorTestModel;
use RoundlyConsulting\Likes\Tests\Models\PostTestModel;

beforeEach(function (): void {
    $this->action = app(LikeAction::class);
    $this->actor = ActorTestModel::create();
    $this->post = PostTestModel::create();
});

it('creates a like and returns true', function (): void {
    $result = $this->action->execute(new LikeData($this->actor, $this->post));

    expect($result)->toBeTrue()
        ->and(Like::query()->count())->toBe(1);
});

it('is idempotent when already liked', function (): void {
    $this->action->execute(new LikeData($this->actor, $this->post));
    $this->action->execute(new LikeData($this->actor, $this->post));

    expect(Like::query()->count())->toBe(1);
});

it('restores a soft-deleted like instead of creating a new row', function (): void {
    $this->action->execute(new LikeData($this->actor, $this->post));
    Like::query()->firstOrFail()->delete();

    $this->action->execute(new LikeData($this->actor, $this->post));

    expect(Like::query()->count())->toBe(1)
        ->and(Like::withTrashed()->count())->toBe(1);
});

it('dispatches Liked and LikeToggled on a new like', function (): void {
    Event::fake();

    $this->action->execute(new LikeData($this->actor, $this->post));

    Event::assertDispatched(Liked::class);
    Event::assertDispatched(fn (LikeToggled $e): bool => $e->hasBeenLiked === true);
});

it('does not dispatch events on a no-op like', function (): void {
    $this->action->execute(new LikeData($this->actor, $this->post));

    Event::fake();
    $this->action->execute(new LikeData($this->actor, $this->post));

    Event::assertNotDispatched(Liked::class);
    Event::assertNotDispatched(LikeToggled::class);
});
