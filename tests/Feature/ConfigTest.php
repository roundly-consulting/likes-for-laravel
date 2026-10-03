<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use RoundlyConsulting\Likes\Models\Like;
use RoundlyConsulting\Likes\Tests\Models\ActorTestModel;
use RoundlyConsulting\Likes\Tests\Models\PostTestModel;
use RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException;

it('ships sensible defaults', function (): void {
    expect(config('likes.table'))->toBe('likes')
        ->and(config('likes.reactions'))->toBe(['like'])
        ->and(config('likes.default_reaction'))->toBe('like')
        ->and(config('likes.actor_resolver'))->toBeNull()
        ->and(config('likes.facade_alias'))->toBe('Likes');
});

it('registers the configured facade alias', function (): void {
    expect(class_exists('Likes'))->toBeTrue();
});

it('respects a custom default reaction', function (): void {
    config()->set('likes.reactions', ['fav']);
    config()->set('likes.default_reaction', 'fav');

    $actor = ActorTestModel::create();
    $post = PostTestModel::create();

    $actor->like($post);

    expect(Like::query()->where('type', 'fav')->count())->toBe(1);
});

it('keeps the migration in sync with the configured table name', function (): void {
    expect(Schema::hasColumn('likes', 'type'))->toBeTrue();
});

it('refuses a misconfigured reaction list instead of falling back to like (strict config)', function (): void {
    config()->set('likes.reactions', 'not-an-array');
    config()->set('likes.default_reaction', null);

    $actor = ActorTestModel::create();
    $post = PostTestModel::create();

    expect(fn () => $actor->like($post))->toThrow(InvalidConfigurationException::class, 'likes.reactions')
        ->and(Like::query()->count())->toBe(0);
});

it('uses a single like reaction when the reactions are absent (strict config)', function (): void {
    config()->set('likes.reactions', null);
    config()->set('likes.default_reaction', null);

    $actor = ActorTestModel::create();
    $post = PostTestModel::create();

    $actor->like($post);

    expect(Like::query()->where('type', 'like')->count())->toBe(1);
});
