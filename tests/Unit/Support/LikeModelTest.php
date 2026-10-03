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

it('refuses a foreign model instead of falling back to the packaged one', function (): void {
    // The toolkit refuses any class that is not the packaged model or a subclass of it.
    config()->set('likes.model', PostTestModel::class);

    expect(fn (): string => LikeModel::class())->toThrow(
        InvalidConfigurationException::class,
        'Configuration value [likes.model] must be a class-string of ['.Like::class.'], ['.PostTestModel::class.'] given.',
    );
});

it('rejects a configured value that is not an eloquent model', function (): void {
    config()->set('likes.model', 'NotAModel');

    LikeModel::class();
})->throws(InvalidConfigurationException::class);
