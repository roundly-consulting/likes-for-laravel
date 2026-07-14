<?php

declare(strict_types=1);

use RoundlyConsulting\Likes\Models\Like;
use RoundlyConsulting\Likes\Support\LikeModel;
use RoundlyConsulting\Likes\Tests\Models\CustomLike;
use RoundlyConsulting\Likes\Tests\Models\PostTestModel;
use RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException;

it('resolves the packaged model by default', function (): void {
    expect(LikeModel::class())->toBe(Like::class);
});

it('resolves a host model that extends the packaged model', function (): void {
    config()->set('likes.model', CustomLike::class);

    expect(LikeModel::class())->toBe(CustomLike::class);
});

it('falls back to the packaged model when the configured model is not a like', function (): void {
    config()->set('likes.model', PostTestModel::class);

    expect(LikeModel::class())->toBe(Like::class);
});

it('rejects a configured value that is not an eloquent model', function (): void {
    config()->set('likes.model', 'NotAModel');

    LikeModel::class();
})->throws(InvalidConfigurationException::class);
