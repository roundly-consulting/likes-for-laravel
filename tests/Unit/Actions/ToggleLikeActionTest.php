<?php

declare(strict_types=1);

use RoundlyConsulting\Likes\Actions\ToggleLikeAction;
use RoundlyConsulting\Likes\DataTransferObjects\LikeData;
use RoundlyConsulting\Likes\Models\Like;
use RoundlyConsulting\Likes\Tests\Models\ActorTestModel;
use RoundlyConsulting\Likes\Tests\Models\PostTestModel;

beforeEach(function (): void {
    $this->action = app(ToggleLikeAction::class);
    $this->actor = ActorTestModel::create();
    $this->post = PostTestModel::create();
});

it('likes when not yet liked and returns true', function (): void {
    $result = $this->action->execute(new LikeData($this->actor, $this->post));

    expect($result)->toBeTrue()
        ->and(Like::query()->count())->toBe(1);
});

it('unlikes when already liked and returns false', function (): void {
    $this->action->execute(new LikeData($this->actor, $this->post));

    $result = $this->action->execute(new LikeData($this->actor, $this->post));

    expect($result)->toBeFalse()
        ->and(Like::query()->count())->toBe(0);
});

it('restores then toggles back off without duplicating rows', function (): void {
    $this->action->execute(new LikeData($this->actor, $this->post)); // like
    $this->action->execute(new LikeData($this->actor, $this->post)); // unlike
    $this->action->execute(new LikeData($this->actor, $this->post)); // re-like restores

    expect(Like::withTrashed()->count())->toBe(1)
        ->and(Like::query()->count())->toBe(1);
});
