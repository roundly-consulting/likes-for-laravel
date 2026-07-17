<?php

declare(strict_types=1);

namespace RoundlyConsulting\Likes\Tests;

use Illuminate\Support\ServiceProvider;
use RoundlyConsulting\Likes\LikesServiceProvider;
use RoundlyConsulting\Testing\PackageTestCase;

abstract class TestCase extends PackageTestCase
{
    /**
     * Every provider likes hard-requires, in registration order.
     *
     * The list is exactly one entry, and that is measured rather than an oversight: likes
     * `require`s package-toolkit-for-laravel, but the toolkit ships no `laravel.providers`
     * entry — it is the base class this provider extends, not a provider itself. There is
     * nothing for a host to auto-discover, so nothing for the suite to mirror.
     *
     * @return list<class-string<ServiceProvider>>
     */
    protected function packageProviders(): array
    {
        return [LikesServiceProvider::class];
    }

    /**
     * The likes migration, named by provider class (never by filename — the base case
     * reflects each provider to its own `database/migrations`), plus the host-owned
     * actor/likeable fixture tables.
     *
     * The fixtures used to be `Schema::create()` calls in `defineDatabaseMigrations()`,
     * paired with `beforeApplicationDestroyed()` drops. They are migrations now because
     * the real-engine reset drops every table and re-migrates between tests: a table
     * created imperatively would survive the drop on the first test and be gone for the
     * second. On sqlite `:memory:` this is identical to what it replaced, and the manual
     * teardown drops are gone — the base case owns that now.
     *
     * @return list<class-string<ServiceProvider>|string>
     */
    protected function migrationSources(): array
    {
        return [
            LikesServiceProvider::class,
            __DIR__.'/database/migrations',
        ];
    }
}
