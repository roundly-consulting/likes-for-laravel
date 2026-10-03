<?php

declare(strict_types=1);

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Support\Facades\Artisan;
use RoundlyConsulting\Likes\Events\Liked;
use RoundlyConsulting\Likes\Models\Like;
use RoundlyConsulting\Likes\Support\ReactionType;
use RoundlyConsulting\Likes\Tests\Models\ActorTestModel;
use RoundlyConsulting\Likes\Tests\Models\PostTestModel;
use RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException;

/**
 * Sweep 2 — the non-boolean settings. A `broadcast.channel_type` typo quietly became a private
 * channel; a junk table name, prefix or reaction list fell back to the default and non-string
 * reactions were dropped. Each now throws, naming the key.
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
})->with(['typo' => ['pubilc'], 'capitalised' => ['Private'], 'blank' => ['']]);

it('uses a private channel when the type is absent (strict config)', function (): void {
    config()->set('likes.broadcast.channel_type', null);

    expect(strictLikedEvent()->broadcastOn())->toBeInstanceOf(PrivateChannel::class);
});

it('refuses a blank or non-string channel prefix (strict config)', function (mixed $value): void {
    config()->set('likes.broadcast.channel_prefix', $value);

    expect(fn () => strictLikedEvent()->broadcastOn())
        ->toThrow(InvalidConfigurationException::class, 'Configuration value [likes.broadcast.channel_prefix] must be a non-empty string');
})->with(['blank' => [''], 'an array' => [['likes']]]);

it('refuses a blank or non-string table name (strict config)', function (mixed $value): void {
    config()->set('likes.table', $value);

    expect(fn () => (new Like)->getTable())
        ->toThrow(InvalidConfigurationException::class, 'Configuration value [likes.table] must be a non-empty string');
})->with(['blank' => [''], 'an int' => [1]]);

it('uses the likes table when the name is absent (strict config)', function (): void {
    config()->set('likes.table', null);

    expect((new Like)->getTable())->toBe('likes');
});

it('refuses a junk reaction list instead of dropping entries (strict config)', function (mixed $value): void {
    config()->set('likes.reactions', $value);

    expect(fn () => ReactionType::allowed())->toThrow(InvalidConfigurationException::class, 'likes.reactions');
})->with([
    'a string' => ['like,love'],
    'empty' => [[]],
    'a non-string entry' => [['like', 3]],
    'a blank entry' => [['like', '']],
]);

it('refuses a blank or non-string default reaction (strict config)', function (mixed $value): void {
    config()->set('likes.default_reaction', $value);

    expect(fn () => ReactionType::default())
        ->toThrow(InvalidConfigurationException::class, 'Configuration value [likes.default_reaction] must be a non-empty string');
})->with(['blank' => [''], 'an int' => [1]]);

it('reads absent reaction settings as their defaults (strict config)', function (): void {
    config()->set('likes.reactions', null);
    config()->set('likes.default_reaction', null);

    expect(ReactionType::allowed())->toBe(['like'])
        ->and(ReactionType::default())->toBe('like');
});

it('flags a broken setting in about instead of failing (strict config)', function (): void {
    config()->set('likes.reactions', 'like,love');
    config()->set('likes.default_reaction', '');

    Artisan::call('about', ['--only' => 'likes']);
    $output = Artisan::output();

    expect($output)->toMatch('/Reactions\W+INVALID/')
        ->and($output)->toMatch('/Default reaction\W+INVALID/');
});
