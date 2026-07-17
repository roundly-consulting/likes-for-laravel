<?php

declare(strict_types=1);

namespace RoundlyConsulting\Likes\Tests\Fixtures;

use RoundlyConsulting\Likes\Tests\TestCase;

/**
 * The suite's base case with `likes.table` renamed BEFORE the providers boot and before
 * the migration runs.
 *
 * This is the only way to test the `likes.table` seam honestly. The migration reads the
 * key inside `up()`, and `defineDatabaseMigrations()` runs after `defineEnvironment()`, so
 * a before-boot rename really does produce a differently-named table — exactly what a host
 * setting `LIKES_TABLE` gets. A runtime `config()->set()` would rename the key long after
 * the schema was built and prove nothing.
 *
 * @see TestCase
 */
abstract class RenamedTableTestCase extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    protected function configBeforeBoot(): array
    {
        return array_merge(parent::configBeforeBoot(), [
            'likes.table' => 'reactions',
        ]);
    }
}
