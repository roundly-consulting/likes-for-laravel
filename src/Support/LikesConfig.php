<?php

declare(strict_types=1);

namespace RoundlyConsulting\Likes\Support;

use RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException;
use RoundlyConsulting\PackageToolkit\Support\Config;

/**
 * Strict readers for the package's non-boolean settings.
 *
 * A default applies only when the key is absent (null). Anything present but unusable — a
 * `pubilc` channel type, a blank table name, a non-numeric weight, an actor resolver that is not
 * callable — throws {@see InvalidConfigurationException} naming the key, instead of quietly
 * falling back or being dropped.
 *
 * @internal
 */
final class LikesConfig
{
    public static function table(): string
    {
        return self::string('likes.table', 'likes');
    }

    public static function channelPrefix(): string
    {
        return self::string('likes.broadcast.channel_prefix', 'likes');
    }

    /** `private`, `public` or `presence`. */
    public static function channelType(): string
    {
        return Config::oneOf('likes.broadcast.channel_type', ['private', 'public', 'presence'], 'private');
    }

    public static function defaultReaction(): string
    {
        return self::string('likes.default_reaction', 'like');
    }

    /**
     * The allowed reaction types; `['like']` when unset.
     *
     * @return list<string>
     */
    public static function reactions(): array
    {
        $key = 'likes.reactions';
        $reactions = config($key) ?? ['like'];

        if (! is_array($reactions) || $reactions === [] || ! array_is_list($reactions)) {
            throw self::invalid($key, 'a non-empty list of reaction types', $reactions);
        }

        $types = [];

        foreach ($reactions as $type) {
            if (! is_string($type) || trim($type) === '') {
                throw self::invalid($key, 'a non-empty list of reaction types', $type);
            }

            $types[] = $type;
        }

        return $types;
    }

    /**
     * The per-type ranking weights; none when unset.
     *
     * @return array<string, int|float>
     */
    public static function weights(): array
    {
        $key = 'likes.weights';
        $weights = config($key) ?? [];

        if (! is_array($weights)) {
            throw self::invalid($key, 'a map of reaction type => number', $weights);
        }

        $resolved = [];

        foreach ($weights as $type => $weight) {
            if (! is_string($type) || (! is_int($weight) && ! is_float($weight))) {
                throw self::invalid($key, 'a map of reaction type => number', is_string($type) ? $weight : $type);
            }

            $resolved[$type] = $weight;
        }

        return $resolved;
    }

    public static function defaultWeight(): int|float
    {
        return self::number('likes.default_weight', 1);
    }

    public static function recentMultiplier(): int|float
    {
        return self::number('likes.trending.recent_multiplier', 3);
    }

    /** The trending window, a relative duration such as `7 days`. */
    public static function trendingWindow(): string
    {
        return self::string('likes.trending.window', '7 days');
    }

    /** The host's trending SQL for a driver, or null when it ships none for that driver. */
    public static function driverExpression(string $driver): ?string
    {
        $key = 'likes.trending.driver_expressions';
        $expressions = config($key) ?? [];

        if (! is_array($expressions)) {
            throw self::invalid($key, 'a map of driver => SQL expression', $expressions);
        }

        $expression = $expressions[$driver] ?? null;

        if ($expression === null) {
            return null;
        }

        if (! is_string($expression) || trim($expression) === '') {
            throw InvalidConfigurationException::notAString($key.'.'.$driver, $expression);
        }

        return $expression;
    }

    /**
     * The configured actor resolver as a callable, or null when unset (the authenticated user
     * is used). An invokable class-string is built through the container; any other callable is
     * used as-is. Anything that does not resolve to a callable throws.
     */
    public static function actorResolver(): ?callable
    {
        $key = 'likes.actor_resolver';
        $resolver = config($key);

        if ($resolver === null) {
            return null;
        }

        $callable = is_string($resolver) && class_exists($resolver) ? app($resolver) : $resolver;

        if (! is_callable($callable)) {
            throw self::invalid($key, 'an invokable class-string or a callable', $resolver);
        }

        return $callable;
    }

    private static function number(string $key, int|float $default): int|float
    {
        $value = config($key) ?? $default;

        if (! is_int($value) && ! is_float($value)) {
            throw self::invalid($key, 'a number', $value);
        }

        return $value;
    }

    private static function string(string $key, string $default): string
    {
        $value = config($key);

        if ($value === null) {
            return $default;
        }

        if (! is_string($value) || trim($value) === '') {
            throw InvalidConfigurationException::notAString($key, $value);
        }

        return $value;
    }

    private static function invalid(string $key, string $expectation, mixed $value): InvalidConfigurationException
    {
        $given = match (true) {
            $value === '' => "''",
            is_string($value) => $value,
            is_int($value), is_float($value), is_bool($value) => var_export($value, true),
            default => get_debug_type($value),
        };

        return new InvalidConfigurationException("Configuration value [{$key}] must be {$expectation}, [{$given}] given.");
    }
}
