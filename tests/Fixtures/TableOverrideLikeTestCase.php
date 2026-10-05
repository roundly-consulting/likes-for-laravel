<?php

declare(strict_types=1);

namespace RoundlyConsulting\Likes\Tests\Fixtures;

use Illuminate\Support\ServiceProvider;
use RoundlyConsulting\Likes\Tests\Models\TableOverrideLike;
use RoundlyConsulting\Likes\Tests\TestCase;

/**
 * The suite's base case with `likes.model` pointed at {@see TableOverrideLike} — a subclass
 * with its own `$table` — before the providers boot, and that `reactions` table migrated next
 * to the packaged `likes` one.
 *
 * @see TestCase
 */
abstract class TableOverrideLikeTestCase extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    protected function configBeforeBoot(): array
    {
        return array_merge(parent::configBeforeBoot(), [
            'likes.model' => TableOverrideLike::class,
        ]);
    }

    /**
     * @return list<class-string<ServiceProvider>|string>
     */
    protected function migrationSources(): array
    {
        return [
            ...parent::migrationSources(),
            __DIR__.'/../database/table-override-migrations',
        ];
    }
}
