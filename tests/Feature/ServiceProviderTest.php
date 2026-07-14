<?php

declare(strict_types=1);

use Illuminate\Foundation\AliasLoader;
use RoundlyConsulting\Likes\Facades\Likes;
use RoundlyConsulting\Likes\LikeManager;
use RoundlyConsulting\Likes\LikesServiceProvider;

/**
 * Re-register the provider against the current config and report the resulting
 * class aliases.
 *
 * @return array<string, string>
 */
function reregisterLikes(): array
{
    AliasLoader::getInstance()->setAliases([]);

    (new LikesServiceProvider(app()))->register();

    return AliasLoader::getInstance()->getAliases();
}

afterEach(function (): void {
    AliasLoader::getInstance()->setAliases([]);
});

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

it('registers the facade alias configured by default', function (): void {
    expect(reregisterLikes())->toHaveKey('Likes', Likes::class);
});

it('honours a renamed facade alias', function (): void {
    config()->set('likes.facade_alias', 'Reactions');

    expect(reregisterLikes())->toBe(['Reactions' => Likes::class]);
});

it('skips registering a facade alias when none is configured', function (): void {
    config()->set('likes.facade_alias', null);

    expect(reregisterLikes())->toBe([]);
});

it('skips registering a facade alias configured as an empty string', function (): void {
    config()->set('likes.facade_alias', '');

    expect(reregisterLikes())->toBe([]);
});

it('contributes a section to the about command', function (): void {
    config()->set('likes.reactions', ['like', 'love']);

    $this->artisan('about', ['--only' => 'likes'])
        ->expectsOutputToContain('like, love')
        ->assertSuccessful();
});
