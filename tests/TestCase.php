<?php

declare(strict_types=1);

namespace RoundlyConsulting\Likes\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;
use RoundlyConsulting\Likes\LikesServiceProvider;

abstract class TestCase extends Orchestra
{
    /** @return array<int, class-string> */
    protected function getPackageProviders($app): array
    {
        return [LikesServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->beforeApplicationDestroyed(function (): void {
            Schema::dropIfExists('actors');
            Schema::dropIfExists('posts');
            Schema::dropIfExists('comments');
        });

        Schema::create('actors', function (Blueprint $table): void {
            $table->increments('id');
        });

        Schema::create('posts', function (Blueprint $table): void {
            $table->increments('id');
        });

        Schema::create('comments', function (Blueprint $table): void {
            $table->increments('id');
        });
    }
}
