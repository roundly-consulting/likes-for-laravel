<?php

declare(strict_types=1);

use RoundlyConsulting\Likes\DataTransferObjects\LikeData;
use RoundlyConsulting\Likes\Exceptions\InvalidReactionTypeException;
use RoundlyConsulting\Likes\Tests\Models\ActorTestModel;
use RoundlyConsulting\Likes\Tests\Models\PostTestModel;

it('defaults to the configured default reaction type', function (): void {
    $data = new LikeData(ActorTestModel::create(), PostTestModel::create());

    expect($data->type)->toBe('like');
});

it('keeps an explicit allowed reaction type', function (): void {
    config()->set('likes.reactions', ['like', 'love']);

    $data = new LikeData(ActorTestModel::create(), PostTestModel::create(), 'love');

    expect($data->type)->toBe('love');
});

it('rejects a reaction type outside the allowlist', function (): void {
    new LikeData(ActorTestModel::create(), PostTestModel::create(), 'wow');
})->throws(InvalidReactionTypeException::class);
