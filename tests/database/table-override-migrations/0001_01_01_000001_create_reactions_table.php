<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use RoundlyConsulting\Likes\Tests\Models\TableOverrideLike;

/**
 * The host-owned `reactions` table behind {@see TableOverrideLike}:
 * the package's own migration run once more under that name, so the shape can never drift
 * from the real one. The `likes` table still exists beside it, empty — a read that follows
 * `likes.table` instead of the model finds nothing there.
 */
return new class extends Migration
{
    public function up(): void
    {
        $table = config('likes.table');
        config()->set('likes.table', 'reactions');

        try {
            $migration = require __DIR__.'/../../../database/migrations/create_likes_table.php';
            $migration->up();
        } finally {
            config()->set('likes.table', $table);
        }
    }
};
