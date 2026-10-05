<?php

declare(strict_types=1);

use RoundlyConsulting\Likes\Models\Like;
use RoundlyConsulting\Likes\Tests\Fixtures\SwappedLikeTestCase;
use RoundlyConsulting\Likes\Tests\Models\ActorTestModel;
use RoundlyConsulting\Likes\Tests\Models\CustomLike;
use RoundlyConsulting\Likes\Tests\Models\PostTestModel;

/**
 * The model-swap proof (S) for the `likes.model` seam, driven through the REAL flows.
 *
 * This is the strong half of what `Unit/Support/LikeModelTest.php` was reaching for. That
 * file stays (it still covers the resolver itself), but its swap case set the config at
 * RUNTIME and asserted a class-string, and both halves are too weak for the bugs this
 * class of test exists for:
 *
 *  - a runtime `config()->set()` leaves every observer and all the provider's boot-time
 *    wiring on the packaged Like (media #28);
 *  - a class-string check never touches a row at all. Only the concrete class of a model
 *    the real flow produced, plus a `created` event counted on the subclass itself, proves
 *    the row was made as the host's model (permissions #31).
 *
 * The swap is applied before boot by {@see SwappedLikeTestCase}, which this directory is
 * bound to — Pest binds a test case per directory, not per file.
 */
it('honours a host like model through every write flow', function (): void {
    expect('likes.model')->toHonourModelSwap(CustomLike::class, function (): array {
        $actor = ActorTestModel::query()->create();
        $post = PostTestModel::query()->create();
        $comment = PostTestModel::query()->create();

        // like / toggle / react — the flows a host actually uses, through the trait's
        // public API rather than a resolver string check.
        $actor->like($post);
        $actor->toggleLike($comment);

        return [
            // The morph relations hydrate through the seam too, not just the writes.
            ...$post->likes()->get()->all(),
            ...$actor->likes()->get()->all(),
        ];
    });
});

/**
 * The read side of the seam. A swap honoured on write but bypassed on read would mean the
 * counts and the viewer's own reaction are queried through a different model than the rows
 * were written as — invisible while both classes share a table, and wrong the moment the
 * host's subclass adds a global scope.
 */
it('reads counts and reactions back through the swapped model', function (): void {
    $actor = ActorTestModel::query()->create();
    $post = PostTestModel::query()->create();

    $actor->like($post);

    expect($post->likes()->first())->toBeInstanceOf(CustomLike::class)
        ->and($post->likesCount())->toBe(1)
        ->and($actor->hasLiked($post))->toBeTrue()
        ->and($post->reactionSummary($actor)->total)->toBe(1);
});

// The structural half of the seam — Like is non-final, and `likes.model` really defaults
// to the packaged model — is pinned once in tests/ArchTest.php by
// `ArchPresets::swappableModelsAreNotFinal()`. It deliberately does NOT live here: that
// preset asserts the config *default*, which this directory has swapped away.

/**
 * The factory hard-coded the packaged model, so `CustomLike::factory()->create()` returned a
 * plain Like and none of the host's model events fired.
 */
it('builds the swapped model from the factory', function (): void {
    CustomLike::resetCreationCount();

    expect(CustomLike::factory()->create())->toBeInstanceOf(CustomLike::class)
        ->and(Like::factory()->create())->toBeInstanceOf(CustomLike::class)
        ->and(CustomLike::creationCount())->toBe(2);
});
