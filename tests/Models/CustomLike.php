<?php

declare(strict_types=1);

namespace RoundlyConsulting\Likes\Tests\Models;

use RoundlyConsulting\Likes\Models\Like;
use RoundlyConsulting\Testing\Fixtures\Concerns\CountsCreations;

/**
 * The host model `likes.model` invites, used to prove the seam is real.
 *
 * `CountsCreations` is what makes the proof independent of `instanceof`: it counts rows
 * created as *this exact class*, so a like row created as the packaged Like — which would
 * still satisfy an `instanceof` check while firing none of the host's model events
 * (permissions #31) — cannot be mistaken for an honoured swap.
 *
 * No `$table` override: `Like::getTable()` already resolves `likes.table`, and the
 * subclass inherits that.
 */
class CustomLike extends Like
{
    use CountsCreations;
}
