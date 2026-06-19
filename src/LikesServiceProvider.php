<?php

declare(strict_types=1);

namespace RoundlyConsulting\Likes;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\AliasLoader;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use RoundlyConsulting\Likes\Facades\Likes;
use RoundlyConsulting\Likes\Support\ReactionType;

final class LikesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/likes.php', 'likes');

        $this->app->singleton(LikeManager::class, fn (): LikeManager => new LikeManager);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->registerFacadeAlias();
        $this->registerBladeDirective();

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/likes.php' => config_path('likes.php'),
            ], 'likes-config');

            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'likes-migrations');
        }
    }

    private function registerFacadeAlias(): void
    {
        $alias = config('likes.facade_alias');

        if (! is_string($alias) || $alias === '') {
            return;
        }

        AliasLoader::getInstance()->alias($alias, Likes::class);
    }

    private function registerBladeDirective(): void
    {
        Blade::if('liked', function (Model $likeable, ?Model $actor = null, ?string $type = null): bool {
            $type ??= ReactionType::default();

            return $actor instanceof Model
                ? Likes::actor($actor)->as($type)->has($likeable)
                : Likes::as($type)->has($likeable);
        });
    }
}
