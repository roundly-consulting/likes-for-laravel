<?php

declare(strict_types=1);

namespace RoundlyConsulting\Likes\Tests\Models;

use RoundlyConsulting\Likes\Models\Like;

/**
 * A host like model with its own `$table`. `Like::getTable()` lets an explicit `$table` win
 * over `likes.table`, so every read the package runs has to follow the model's table, not the
 * config key.
 */
final class TableOverrideLike extends Like
{
    protected $table = 'reactions';
}
