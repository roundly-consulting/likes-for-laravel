<?php

declare(strict_types=1);

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;
use RoundlyConsulting\Likes\Facades\Likes;
use RoundlyConsulting\Likes\Models\Like;
use RoundlyConsulting\Likes\Support\LikeModel;
use RoundlyConsulting\Likes\Tests\Models\ActorTestModel;
use RoundlyConsulting\Likes\Tests\Models\PostTestModel;
use RoundlyConsulting\PackageToolkit\Enums\KeyType;

/*
 * The likes table's own key (`likes.primary_key_type`) and the morph key (`likes.key_type`), as
 * the suite was booted with them (LIKES_PRIMARY_KEY_TYPE / LIKES_KEY_TYPE). CI runs the whole
 * suite under each combination; these pin what each one means.
 */

it('keys a like the way likes.primary_key_type says', function (): void {
    $actor = ActorTestModel::query()->create();
    $post = PostTestModel::query()->create();

    Likes::actor($actor)->like($post);
    $like = Like::query()->sole();
    $id = $like->getKey();

    expect(match (KeyType::fromConfig('likes.primary_key_type')) {
        KeyType::BigInt => is_int($id) && $like->getIncrementing(),
        KeyType::Ulid => Str::isUlid((string) $id) && ! $like->getIncrementing() && $like->getKeyType() === 'string',
        KeyType::Uuid => Str::isUuid((string) $id) && ! $like->getIncrementing() && $like->getKeyType() === 'string',
    })->toBeTrue()
        ->and(Like::query()->find($id)?->is($like))->toBeTrue();
});

it('points both morphs at actors and likeables keyed the way likes.key_type says', function (): void {
    $actor = ActorTestModel::query()->create();
    $post = PostTestModel::query()->create();

    Likes::actor($actor)->like($post);
    $like = Like::query()->with(['actor', 'likeable'])->sole();

    expect((string) $like->actor_id)->toBe((string) $actor->getKey())
        ->and((string) $like->likeable_id)->toBe((string) $post->getKey())
        ->and($like->actor?->is($actor))->toBeTrue()
        ->and($like->likeable?->is($post))->toBeTrue()
        ->and(match (KeyType::fromConfig('likes.key_type')) {
            KeyType::BigInt => is_int($actor->getKey()),
            KeyType::Ulid => Str::isUlid((string) $actor->getKey()),
            KeyType::Uuid => Str::isUuid((string) $actor->getKey()),
        })->toBeTrue()
        ->and($post->isLikedBy($actor))->toBeTrue();
});

it('still allows one row per actor, likeable and reaction type', function (): void {
    $actor = ActorTestModel::query()->create();
    $post = PostTestModel::query()->create();
    Likes::actor($actor)->like($post);

    $model = LikeModel::class();

    expect(fn () => $model::query()->create([
        'actor_type' => $actor->getMorphClass(),
        'actor_id' => $actor->getKey(),
        'likeable_type' => $post->getMorphClass(),
        'likeable_id' => $post->getKey(),
        'type' => 'like',
    ]))->toThrow(UniqueConstraintViolationException::class);

    Likes::actor($actor)->like($post);

    expect(Like::withTrashed()->count())->toBe(1);
});
