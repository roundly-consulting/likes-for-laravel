<?php

declare(strict_types=1);

use RoundlyConsulting\Likes\Actions\LikeManyAction;
use RoundlyConsulting\Likes\Actions\UnlikeManyAction;
use RoundlyConsulting\Likes\Models\Like;
use RoundlyConsulting\Likes\Tests\Models\ActorTestModel;
use RoundlyConsulting\Likes\Tests\Models\PostTestModel;

beforeEach(function (): void {
    $this->actor = ActorTestModel::create();
    $this->posts = collect([PostTestModel::create(), PostTestModel::create(), PostTestModel::create()]);
});

it('likes every target once', function (): void {
    app(LikeManyAction::class)->execute($this->actor, $this->posts);

    expect(Like::query()->count())->toBe(3);
});

it('is idempotent across repeated bulk likes', function (): void {
    app(LikeManyAction::class)->execute($this->actor, $this->posts);
    app(LikeManyAction::class)->execute($this->actor, $this->posts);

    expect(Like::query()->count())->toBe(3);
});

it('unlikes every target', function (): void {
    app(LikeManyAction::class)->execute($this->actor, $this->posts);

    app(UnlikeManyAction::class)->execute($this->actor, $this->posts);

    expect(Like::query()->count())->toBe(0);
});
