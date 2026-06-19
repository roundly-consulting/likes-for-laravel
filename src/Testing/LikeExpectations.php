<?php

declare(strict_types=1);

namespace RoundlyConsulting\Likes\Testing;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Likes\Contracts\Likeable;

/**
 * Registers Pest expectation matchers for like assertions. Host applications
 * call {@see LikeExpectations::register()} from their tests/Pest.php.
 *
 * The matchers are only defined when Pest's expectation API is available, so
 * this file never pulls Pest into the package's runtime and static analysis
 * stays clean.
 */
final class LikeExpectations
{
    public static function register(): void
    {
        if (! function_exists('expect')) {
            return;
        }

        expect()->extend('toBeLikedBy', function (Model $actor, ?string $type = null): mixed {
            /** @var Likeable $likeable */
            $likeable = $this->value;

            expect($likeable->isLikedBy($actor, $type))->toBeTrue();

            return $this;
        });

        expect()->extend('toHaveReaction', function (string $type): mixed {
            /** @var Likeable $likeable */
            $likeable = $this->value;

            expect($likeable->reactionSummary()->has($type))->toBeTrue();

            return $this;
        });
    }
}
