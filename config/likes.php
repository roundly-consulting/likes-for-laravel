<?php

declare(strict_types=1);

use RoundlyConsulting\Likes\Models\Like;

return [
    /*
    |--------------------------------------------------------------------------
    | Like model
    |--------------------------------------------------------------------------
    |
    | The Eloquent model used to store likes. Override this with your own
    | model (extending the package model) if you need custom behaviour.
    |
    */

    'model' => Like::class,

    /*
    |--------------------------------------------------------------------------
    | Table name
    |--------------------------------------------------------------------------
    |
    | The database table that stores likes. Both the migration and the model
    | read this value, so changing it keeps the schema and queries in sync.
    |
    */

    'table' => env('LIKES_TABLE', 'likes'),

    /*
    |--------------------------------------------------------------------------
    | Key Type
    |--------------------------------------------------------------------------
    |
    | The key type used for the polymorphic actor / likeable columns. Use "uuid"
    | or "ulid" when the models that act and get liked use UUID/ULID primary
    | keys, otherwise leave it as "bigint". Anything unrecognized falls back to
    | "bigint". Your morph targets must share one key type — set this to match.
    |
    | Supported: "bigint", "uuid", "ulid"
    |
    */

    'key_type' => env('LIKES_KEY_TYPE', 'bigint'),

    /*
    |--------------------------------------------------------------------------
    | Reaction types
    |--------------------------------------------------------------------------
    |
    | The allowlist of reaction types the package accepts. By default a single
    | implicit "like" reaction is configured, so behaviour is unchanged. Add
    | more (e.g. 'love', 'wow', 'laugh') to enable typed reactions; any type
    | outside this list is rejected with an InvalidReactionTypeException.
    |
    | @var list<string>
    */

    'reactions' => ['like'],

    /*
    |--------------------------------------------------------------------------
    | Default reaction
    |--------------------------------------------------------------------------
    |
    | The reaction type used when none is given to like()/unlike()/toggle().
    | It must be present in the "reactions" allowlist above.
    |
    */

    'default_reaction' => env('LIKES_DEFAULT_REACTION', 'like'),

    /*
    |--------------------------------------------------------------------------
    | Actor resolver
    |--------------------------------------------------------------------------
    |
    | How the Likes facade/manager resolves the acting model when none is
    | supplied. Leave null to use the authenticated user (auth()->user()), or
    | provide a callable or an invokable class-string returning a Model|null
    | for custom guards or non-user actors.
    |
    | @var callable|class-string|null
    */

    'actor_resolver' => null,

    /*
    |--------------------------------------------------------------------------
    | Facade alias
    |--------------------------------------------------------------------------
    |
    | The class alias registered for the Likes facade so it can be referenced
    | as a short global (e.g. \Likes::like($post)). Set to null to skip
    | registering an alias and reference the facade by its full class name.
    |
    */

    'facade_alias' => env('LIKES_FACADE_ALIAS', 'Likes'),

    /*
    |--------------------------------------------------------------------------
    | Reaction weights
    |--------------------------------------------------------------------------
    |
    | Per-reaction weights used by the orderByLikeScore() scope. Map a reaction
    | type to a numeric weight to make some reactions count more than others.
    | Leave empty so every reaction weighs the same and the score equals the
    | raw like count.
    |
    | @var array<string, int|float>
    */

    'weights' => [
        // 'like' => 1,
        // 'love' => 4,
    ],

    /*
    |--------------------------------------------------------------------------
    | Default reaction weight
    |--------------------------------------------------------------------------
    |
    | The weight applied to any reaction not listed in "weights" above.
    |
    */

    'default_weight' => 1,

    /*
    |--------------------------------------------------------------------------
    | Trending ranking
    |--------------------------------------------------------------------------
    |
    | Settings for the orderByTrending() scope. "window" is a strtotime-able
    | recency window; likes inside it are boosted by "recent_multiplier" over
    | all-time activity. The default expression is portable across SQLite,
    | MySQL and Postgres. For an exact per-driver decay curve, supply a raw SQL
    | aggregate per driver in "driver_expressions" (advanced; used verbatim for
    | queries on a connection of that driver). Every "?" in it is bound to the
    | window cut-off (now minus "window").
    |
    | @var array{window: string, recent_multiplier: int|float, driver_expressions: array<string, string>}
    */

    'trending' => [
        'window' => '7 days',
        'recent_multiplier' => 3,
        'driver_expressions' => [
            // 'pgsql' => 'SUM(1 / POWER(EXTRACT(EPOCH FROM (NOW() - created_at)) / 3600 + 2, 1.8))',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Broadcasting
    |--------------------------------------------------------------------------
    |
    | Opt-in broadcasting of the Liked, Unliked and ReactionChanged events.
    | Disabled by default, so existing installs see no broadcast traffic. When
    | enabled, events broadcast on "{channel_prefix}.{morph}.{id}" using the
    | configured channel type (private|public|presence).
    |
    | @var array{enabled: bool, channel_prefix: string, channel_type: string}
    */

    'broadcast' => [
        'enabled' => env('LIKES_BROADCAST', false),
        'channel_prefix' => 'likes',
        'channel_type' => 'private',
    ],
];
