<?php

declare(strict_types=1);

namespace RoundlyConsulting\Likes\Support;

use RoundlyConsulting\Likes\Exceptions\InvalidReactionTypeException;

/**
 * Resolves and validates reaction types against the package configuration.
 */
final class ReactionType
{
    public static function default(): string
    {
        $default = config('likes.default_reaction', 'like');

        return is_string($default) ? $default : 'like';
    }

    /**
     * @return list<string>
     */
    public static function allowed(): array
    {
        $reactions = config('likes.reactions', ['like']);

        if (! is_array($reactions)) {
            return ['like'];
        }

        return array_values(array_filter($reactions, 'is_string'));
    }

    /**
     * Resolve a reaction type, falling back to the default, and validate it.
     *
     * @throws InvalidReactionTypeException
     */
    public static function resolve(?string $type): string
    {
        $type ??= self::default();

        $allowed = self::allowed();

        if (! in_array($type, $allowed, true)) {
            throw InvalidReactionTypeException::for($type, $allowed);
        }

        return $type;
    }
}
