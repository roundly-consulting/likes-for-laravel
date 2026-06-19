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
];
