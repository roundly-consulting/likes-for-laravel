<?php

declare(strict_types=1);

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Support\Facades\Artisan;
use RoundlyConsulting\Likes\Events\Liked;
use RoundlyConsulting\Likes\Models\Like;
use RoundlyConsulting\Likes\Support\LikesConfig;
use RoundlyConsulting\Likes\Support\ReactionType;
use RoundlyConsulting\Likes\Tests\Models\ActorTestModel;
use RoundlyConsulting\Likes\Tests\Models\PostTestModel;
use RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException;

/**
 * Sweep 2 — the non-boolean settings. A `broadcast.channel_type` typo quietly became a private
 * channel; a junk table name, prefix or reaction list fell back to the default and non-string
 * reactions were dropped. Each now throws, naming the key.
 *
 * Sweep 3 — a blank value (a host's `KEY=`, or whitespace) is not set: it takes the default,
 * exactly like an absent key. Junk still throws.
 */
function strictLikedEvent(): Liked
{
    $actor = ActorTestModel::query()->create();
    $post = PostTestModel::query()->create();

    return new Liked($actor, $post, 'like', new Like);
}

it('refuses a channel type typo instead of broadcasting privately (strict config)', function (mixed $value): void {
    config()->set('likes.broadcast.channel_type', $value);

    expect(fn () => strictLikedEvent()->broadcastOn())->toThrow(
        InvalidConfigurationException::class,
        'Configuration value [likes.broadcast.channel_type] must be one of [private, public, presence]',
    );
})->with(['typo' => ['pubilc'], 'capitalised' => ['Private']]);

it('uses a private channel when the type is absent or blank (strict config)', function (?string $value): void {
    config()->set('likes.broadcast.channel_type', $value);

    expect(strictLikedEvent()->broadcastOn())->toBeInstanceOf(PrivateChannel::class);
})->with(['absent' => [null], 'blank' => [''], 'whitespace' => [' ']]);

it('refuses a non-string channel prefix (strict config)', function (mixed $value): void {
    config()->set('likes.broadcast.channel_prefix', $value);

    expect(fn () => strictLikedEvent()->broadcastOn())
        ->toThrow(InvalidConfigurationException::class, 'Configuration value [likes.broadcast.channel_prefix] must be a non-empty string');
})->with(['an int' => [5], 'an array' => [['likes']]]);

it('uses the likes channel prefix when it is blank (strict config)', function (): void {
    config()->set('likes.broadcast.channel_prefix', ' ');

    expect(LikesConfig::channelPrefix())->toBe('likes')
        ->and(strictLikedEvent()->broadcastOn()->name)->toStartWith('private-likes.');
});

it('refuses a non-string table name (strict config)', function (mixed $value): void {
    config()->set('likes.table', $value);

    expect(fn () => (new Like)->getTable())
        ->toThrow(InvalidConfigurationException::class, 'Configuration value [likes.table] must be a non-empty string');
})->with(['an int' => [1], 'an array' => [['likes']]]);

it('uses the likes table when the name is absent or blank (strict config)', function (?string $value): void {
    config()->set('likes.table', $value);

    expect((new Like)->getTable())->toBe('likes');
})->with(['absent' => [null], 'blank' => [''], 'whitespace' => ['  ']]);

it('refuses a junk reaction list instead of dropping entries (strict config)', function (mixed $value): void {
    config()->set('likes.reactions', $value);

    expect(fn () => ReactionType::allowed())->toThrow(InvalidConfigurationException::class, 'likes.reactions');
})->with([
    'a string' => ['like,love'],
    'empty' => [[]],
    'a non-string entry' => [['like', 3]],
    'a blank entry' => [['like', '']],
]);

it('refuses a non-string default reaction (strict config)', function (mixed $value): void {
    config()->set('likes.default_reaction', $value);

    expect(fn () => ReactionType::default())
        ->toThrow(InvalidConfigurationException::class, 'Configuration value [likes.default_reaction] must be a non-empty string');
})->with(['an int' => [1], 'an array' => [['like']]]);

it('reads absent or blank reaction settings as their defaults (strict config)', function (?string $value): void {
    config()->set('likes.reactions', $value);
    config()->set('likes.default_reaction', $value);

    expect(ReactionType::allowed())->toBe(['like'])
        ->and(ReactionType::default())->toBe('like');
})->with(['absent' => [null], 'blank' => [''], 'whitespace' => [' ']]);

it('reads blank ranking, trending and resolver settings as not set (strict config)', function (): void {
    config()->set('likes.weights', '');
    config()->set('likes.default_weight', ' ');
    config()->set('likes.trending.recent_multiplier', '');
    config()->set('likes.trending.window', ' ');
    config()->set('likes.actor_resolver', '');

    expect(LikesConfig::weights())->toBe([])
        ->and(LikesConfig::defaultWeight())->toBe(1)
        ->and(LikesConfig::recentMultiplier())->toBe(3)
        ->and(LikesConfig::trendingWindow())->toBe('7 days')
        ->and(LikesConfig::actorResolver())->toBeNull();
});

it('still refuses a junk weight or multiplier (strict config)', function (string $key, mixed $value): void {
    config()->set($key, $value);

    expect(fn () => [LikesConfig::defaultWeight(), LikesConfig::recentMultiplier()])
        ->toThrow(InvalidConfigurationException::class, "Configuration value [{$key}] must be a number");
})->with([
    'weight word' => ['likes.default_weight', 'heavy'],
    'multiplier word' => ['likes.trending.recent_multiplier', 'triple'],
]);

it('flags a broken setting in about instead of failing (strict config)', function (): void {
    config()->set('likes.reactions', 'like,love');
    config()->set('likes.default_reaction', 5);

    Artisan::call('about', ['--only' => 'likes']);
    $output = Artisan::output();

    expect($output)->toMatch('/Reactions\W+INVALID/')
        ->and($output)->toMatch('/Default reaction\W+INVALID/');
});
