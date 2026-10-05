<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Likes\Tests\Models\DualRoleGivesWinsTestModel;
use RoundlyConsulting\Likes\Tests\Models\DualRoleHasWinsTestModel;
use RoundlyConsulting\Likes\Tests\Models\PostTestModel;

/**
 * A model can give likes and receive them (a member likes posts and is liked by other
 * members). Both traits declare `likes()`, so the host resolves the collision with
 * `insteadof` — and whichever side wins `likes()`, the HasLikes scopes must still count the
 * likes the model RECEIVED. They used to go through `likes()`, so with `GivesLikes::likes`
 * winning they counted the likes it gave.
 *
 * @param  class-string<Model>  $class
 */
function dualRoleScenario(string $class): array
{
    $self = $class::query()->create();
    $other = $class::query()->create();

    // Self gives two likes and receives one, from $other.
    $self->like(PostTestModel::query()->create());
    $self->like(PostTestModel::query()->create());
    $other->like($self);

    return [$self, $other];
}

dataset('dual-role models', [
    'GivesLikes::likes wins' => [DualRoleGivesWinsTestModel::class],
    'HasLikes::likes wins' => [DualRoleHasWinsTestModel::class],
]);

it('counts the likes a dual-role model received', function (string $class): void {
    [$self, $other] = dualRoleScenario($class);

    $counts = $class::query()->withLikesCount()->orderBy('id')->pluck('likes_count', 'id')->all();

    expect($counts)->toBe([$self->getKey() => 1, $other->getKey() => 0])
        ->and($class::query()->orderByLikesDesc()->first()?->is($self))->toBeTrue()
        ->and($self->likesCount())->toBe(1);
})->with('dual-role models');

it('filters a dual-role model by who liked it', function (string $class): void {
    [$self, $other] = dualRoleScenario($class);

    expect($class::query()->whereLikedBy($other)->pluck('id')->all())->toBe([$self->getKey()])
        ->and($class::query()->whereNotLikedBy($other)->pluck('id')->all())->toBe([$other->getKey()]);
})->with('dual-role models');

it('exposes both sides as unambiguous relations', function (string $class): void {
    [$self] = dualRoleScenario($class);

    expect($self->likesReceived()->count())->toBe(1)
        ->and($self->likesGiven()->count())->toBe(2);
})->with('dual-role models');
