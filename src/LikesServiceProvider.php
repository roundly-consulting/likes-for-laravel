<?php

declare(strict_types=1);

namespace RoundlyConsulting\Likes;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Likes\Facades\Likes;
use RoundlyConsulting\Likes\Support\ReactionType;
use RoundlyConsulting\PackageToolkit\Concerns\RegistersBladeDirectives;
use RoundlyConsulting\PackageToolkit\Concerns\RegistersBlueprintMacros;
use RoundlyConsulting\PackageToolkit\Package;
use RoundlyConsulting\PackageToolkit\PackageServiceProvider;

final class LikesServiceProvider extends PackageServiceProvider
{
    use RegistersBladeDirectives;
    use RegistersBlueprintMacros;

    public function configurePackage(Package $package): void
    {
        $package
            ->name('likes')
            ->hasConfigFile()
            ->hasMigrations()
            ->hasFacadeAlias(Likes::class, 'likes.facade_alias')
            ->contributesToAbout(static fn (): array => [
                'Reactions' => implode(', ', ReactionType::allowed()),
                'Default reaction' => ReactionType::default(),
                'Broadcasting' => config('likes.broadcast.enabled') === true ? 'ENABLED' : 'OFF',
            ]);
    }

    public function register(): void
    {
        parent::register();

        $this->app->singleton(LikeManager::class);
    }

    public function boot(): void
    {
        parent::boot();

        // The migration's key-type-aware morph columns are macros, so they must
        // exist before a host runs `php artisan migrate`.
        $this->registerBlueprintMacros();

        $this->registerBladeIf('liked', function (Model $likeable, ?Model $actor = null, ?string $type = null): bool {
            $type ??= ReactionType::default();

            return $actor instanceof Model
                ? Likes::actor($actor)->as($type)->has($likeable)
                : Likes::as($type)->has($likeable);
        });
    }
}
