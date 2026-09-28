<?php

declare(strict_types=1);

namespace RoundlyConsulting\Likes\Support;

use Illuminate\Database\Query\Builder;
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

    /**
     * The attribute an eager count lands in: `likes_count` for all reactions, and
     * `likes_{type}_count` for one type — kept apart so a typed count can never be read back
     * as the all-reactions total.
     *
     * @throws InvalidReactionTypeException
     */
    public static function countAttribute(?string $type): string
    {
        return $type === null ? 'likes_count' : 'likes_'.self::resolve($type).'_count';
    }

    /**
     * Order a likes query by reaction preference: the order of `likes.reactions`, then any
     * type no longer configured, alphabetically. This is how the viewer's reaction is picked
     * when they hold several — deterministically, and by the same rule that breaks `top` ties.
     *
     * One `type = ? desc` term per configured type: portable (a boolean sorts on SQLite,
     * MySQL and Postgres alike), the types are bound, and the column stays unqualified so it
     * resolves to the likes table even inside a correlated sub-select.
     */
    public static function orderByPreference(Builder $query): void
    {
        foreach (self::allowed() as $type) {
            $query->orderByRaw('type = ? desc', [$type]);
        }

        $query->orderBy('type');
    }
}
