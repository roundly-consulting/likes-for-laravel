<?php

declare(strict_types=1);

namespace RoundlyConsulting\Likes\Tests\Fixtures;

use RoundlyConsulting\Likes\Tests\Models\CustomLike;
use RoundlyConsulting\Likes\Tests\TestCase;

/**
 * The suite's base case with `likes.model` already pointed at {@see CustomLike} BEFORE the
 * providers boot.
 *
 * Boot order is the whole point: the provider hangs its Blade directive, its broadcast
 * wiring and every observer on whatever `likes.model` names at boot. A `config()->set()`
 * inside a test body reads back correctly and leaves all of that on the packaged Like —
 * which is the shape that let media #28 ship, and what the test this strengthens
 * (`Unit/Support/LikeModelTest.php`, a runtime `set` plus a class-string check) could
 * never catch.
 *
 * The `array_merge(parent::configBeforeBoot(), …)` is not decoration: dropping it silently
 * discards whatever the base case wires, with no error and no red — the same decapitation
 * an un-parented `defineEnvironment()` override causes one level up. It is empty today;
 * that is not a reason to omit it.
 *
 * @see TestCase
 */
abstract class SwappedLikeTestCase extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    protected function configBeforeBoot(): array
    {
        return array_merge(parent::configBeforeBoot(), [
            'likes.model' => CustomLike::class,
        ]);
    }
}
