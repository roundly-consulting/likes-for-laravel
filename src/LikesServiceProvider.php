<?php

declare(strict_types=1);

namespace RoundlyConsulting\Likes;

use Illuminate\Support\ServiceProvider;

final class LikesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/likes.php', 'likes');
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/likes.php' => config_path('likes.php'),
            ], 'likes-config');

            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'likes-migrations');
        }
    }
}
