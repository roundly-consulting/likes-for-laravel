<?php

declare(strict_types=1);

use RoundlyConsulting\Likes\LikeManager;
use RoundlyConsulting\Likes\LikesServiceProvider;

it('publishes the config file', function (): void {
    $target = config_path('likes.php');

    if (file_exists($target)) {
        unlink($target);
    }

    $this->artisan('vendor:publish', ['--tag' => 'likes-config'])->assertSuccessful();

    expect(file_exists($target))->toBeTrue();

    unlink($target);
});

it('publishes the migrations', function (): void {
    $this->artisan('vendor:publish', ['--tag' => 'likes-migrations'])->assertSuccessful();
});

it('binds the like manager', function (): void {
    expect(app(LikeManager::class))->toBeInstanceOf(LikeManager::class);
});

it('skips registering a facade alias when none is configured', function (): void {
    config()->set('likes.facade_alias', null);

    $provider = new LikesServiceProvider($this->app);
    $method = new ReflectionMethod($provider, 'registerFacadeAlias');
    $method->invoke($provider);

    expect(class_exists('NonExistentLikesAlias', false))->toBeFalse();
});
