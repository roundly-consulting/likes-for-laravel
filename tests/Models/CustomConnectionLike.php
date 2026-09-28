<?php

declare(strict_types=1);

namespace RoundlyConsulting\Likes\Tests\Models;

use RoundlyConsulting\Likes\Models\Like;

/**
 * A host like model pinned to a named connection, so the suite can move the *default*
 * connection elsewhere and prove the package follows the model's own connection.
 */
class CustomConnectionLike extends Like
{
    protected $connection = 'testing';
}
