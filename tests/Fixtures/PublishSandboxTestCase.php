<?php

declare(strict_types=1);

namespace RoundlyConsulting\Likes\Tests\Fixtures;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\File;
use RoundlyConsulting\Likes\Tests\TestCase;

/**
 * The suite's base case with `config/` and `database/` pointed at a throwaway directory per
 * test, set BEFORE the providers boot — their publish destinations are fixed then.
 *
 * Publishing into the shared testbench skeleton raced the parallel suite: every process
 * boots from that skeleton, requiring each `laravel/config/*.php` and migrating
 * `laravel/database/migrations`. A `likes.php` being written or unlinked mid-boot fails
 * whichever test happens to be booting, and the published migrations were never cleaned
 * up at all — each run left another `create_likes_table` behind.
 *
 * @see TestCase
 */
abstract class PublishSandboxTestCase extends TestCase
{
    private string $sandbox = '';

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $this->sandbox = sys_get_temp_dir().'/likes-publish-'.bin2hex(random_bytes(6));
        File::ensureDirectoryExists($this->sandbox.'/config');
        File::ensureDirectoryExists($this->sandbox.'/database/migrations');

        $app->useConfigPath($this->sandbox.'/config');
        $app->useDatabasePath($this->sandbox.'/database');
    }

    protected function tearDown(): void
    {
        if ($this->sandbox !== '') {
            File::deleteDirectory($this->sandbox);
        }

        parent::tearDown();
    }
}
