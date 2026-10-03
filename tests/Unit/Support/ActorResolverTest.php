<?php

declare(strict_types=1);

use RoundlyConsulting\Likes\Support\ActorResolver;
use RoundlyConsulting\Likes\Tests\Models\ActorTestModel;
use RoundlyConsulting\Likes\Tests\Models\CurrentActorResolver;
use RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException;

it('returns the explicit actor when given', function (): void {
    $actor = ActorTestModel::query()->create();

    expect(ActorResolver::resolve($actor))->toBe($actor);
});

it('returns the authenticated user when no resolver is configured', function (): void {
    config()->set('likes.actor_resolver', null);

    $actor = ActorTestModel::query()->create();
    $this->actingAs($actor);

    expect(ActorResolver::resolve())->toBe($actor);
});

it('returns null for a guest with no resolver', function (): void {
    config()->set('likes.actor_resolver', null);

    expect(ActorResolver::resolve())->toBeNull();
});

it('resolves through an invokable class-string resolver', function (): void {
    config()->set('likes.actor_resolver', CurrentActorResolver::class);

    $actor = ActorTestModel::query()->create();

    expect(ActorResolver::resolve())->toBeInstanceOf(ActorTestModel::class)
        ->and(ActorResolver::resolve()?->getKey())->toBe($actor->getKey());
});

it('resolves through a callable resolver', function (): void {
    $actor = ActorTestModel::query()->create();

    config()->set('likes.actor_resolver', fn (): ActorTestModel => $actor);

    expect(ActorResolver::resolve())->toBe($actor);
});

it('refuses a resolver that is not callable instead of resolving no actor (strict config)', function (mixed $resolver): void {
    config()->set('likes.actor_resolver', $resolver);

    expect(fn () => ActorResolver::resolve())->toThrow(InvalidConfigurationException::class, 'likes.actor_resolver');
})->with([
    'unknown class' => ['not-a-class'],
    'not invokable' => [ActorTestModel::class],
    'a number' => [42],
]);

it('returns null when the callable resolver yields a non-model', function (): void {
    config()->set('likes.actor_resolver', fn (): ?string => 'nope');

    expect(ActorResolver::resolve())->toBeNull();
});
