<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Likes\Actions\LikeAction;
use RoundlyConsulting\Likes\Actions\UnlikeAction;
use RoundlyConsulting\Likes\DataTransferObjects\LikeData;
use RoundlyConsulting\Likes\Events\Unliked;
use RoundlyConsulting\Likes\Models\Like;
use RoundlyConsulting\Likes\Tests\Models\ActorTestModel;
use RoundlyConsulting\Likes\Tests\Models\PostTestModel;

beforeEach(function (): void {
    $this->unlike = app(UnlikeAction::class);
    $this->like = app(LikeAction::class);
    $this->actor = ActorTestModel::create();
    $this->post = PostTestModel::create();
});

it('soft-deletes an existing like and returns false', function (): void {
    $this->like->execute(new LikeData($this->actor, $this->post));

    $result = $this->unlike->execute(new LikeData($this->actor, $this->post));

    expect($result)->toBeFalse()
        ->and(Like::query()->count())->toBe(0)
        ->and(Like::withTrashed()->count())->toBe(1);
});

it('is idempotent when not liked', function (): void {
    $result = $this->unlike->execute(new LikeData($this->actor, $this->post));

    expect($result)->toBeFalse()
        ->and(Like::withTrashed()->count())->toBe(0);
});

it('dispatches Unliked on removal', function (): void {
    $this->like->execute(new LikeData($this->actor, $this->post));

    Event::fake();
    $this->unlike->execute(new LikeData($this->actor, $this->post));

    Event::assertDispatched(Unliked::class);
});

it('does not dispatch events on a no-op unlike', function (): void {
    Event::fake();

    $this->unlike->execute(new LikeData($this->actor, $this->post));

    Event::assertNotDispatched(Unliked::class);
});
