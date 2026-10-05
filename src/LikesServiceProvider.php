<?php

declare(strict_types=1);

namespace RoundlyConsulting\Likes;

use Closure;
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Likes\Facades\Likes;
use RoundlyConsulting\Likes\Support\ActorResolver;
use RoundlyConsulting\Likes\Support\ReactionType;
use RoundlyConsulting\PackageToolkit\Concerns\RegistersBladeDirectives;
use RoundlyConsulting\PackageToolkit\Concerns\RegistersBlueprintMacros;
use RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException;
use RoundlyConsulting\PackageToolkit\Package;
use RoundlyConsulting\PackageToolkit\PackageServiceProvider;
use RoundlyConsulting\PackageToolkit\Support\Config;

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
                'Reactions' => self::orInvalid(static fn (): string => implode(', ', ReactionType::allowed())),
                'Default reaction' => self::orInvalid(ReactionType::default(...)),
                'Broadcasting' => Config::boolean('likes.broadcast.enabled') ? 'ENABLED' : 'OFF',
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
            // A guest has liked nothing: render the @else branch instead of throwing.
            $actor = ActorResolver::resolve($actor);

            if (! $actor instanceof Model) {
                return false;
            }

            return Likes::actor($actor)->as($type ?? ReactionType::default())->has($likeable);
        });
    }

    /**
     * A strict read rendered for `about`, or `INVALID` when the setting is broken — so
     * `php artisan about` still works on a misconfigured host while every real read throws.
     *
     * @param  Closure(): string  $read
     */
    private static function orInvalid(Closure $read): string
    {
        try {
            return $read();
        } catch (InvalidConfigurationException) {
            return 'INVALID';
        }
    }
}
