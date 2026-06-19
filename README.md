# Likes for Laravel

Lightweight Laravel package to handle likes and reactions on entities. Any Eloquent model
can give likes, any model can receive them, and a single polymorphic `likes` table records
who liked what — with optional typed reactions (love, wow, …), popularity scopes, fast
counts, a fluent facade, and events your application can listen to.

## Requirements

- PHP `^8.4`
- Laravel `^12.0` or `^13.0`

## Installation

Install the package via Composer:

```bash
composer require roundly-consulting/likes-for-laravel
```

The migration is loaded automatically, so you can run it straight away:

```bash
php artisan migrate
```

If you prefer to publish the migration into your application first:

```bash
php artisan vendor:publish --tag="likes-migrations"
php artisan migrate
```

Optionally publish the config file:

```bash
php artisan vendor:publish --tag="likes-config"
```

## Configuration

The published config file (`config/likes.php`):

```php
<?php

declare(strict_types=1);

use RoundlyConsulting\Likes\Models\Like;

return [
    'model' => Like::class,
    'table' => env('LIKES_TABLE', 'likes'),
    'reactions' => ['like'],
    'default_reaction' => env('LIKES_DEFAULT_REACTION', 'like'),
    'actor_resolver' => null,
    'facade_alias' => env('LIKES_FACADE_ALIAS', 'Likes'),
];
```

| Key                | Type                            | Default          | Env                       | Purpose                                                                                       |
|--------------------|---------------------------------|------------------|---------------------------|-----------------------------------------------------------------------------------------------|
| `model`            | `class-string`                  | `Like::class`    | —                         | Eloquent model used to persist each like. Swap in your own model (extending the package one).  |
| `table`            | `string`                        | `likes`          | `LIKES_TABLE`             | Database table that stores likes. Read by both the migration and the model.                    |
| `reactions`        | `list<string>`                  | `['like']`       | —                         | Allowlist of accepted reaction types. Any type outside the list is rejected.                   |
| `default_reaction` | `string`                        | `like`           | `LIKES_DEFAULT_REACTION`  | Reaction used when none is given. Must be present in `reactions`.                              |
| `actor_resolver`   | `callable\|class-string\|null`  | `null`           | —                         | How the facade resolves the actor when none is supplied. `null` uses `auth()->user()`.        |
| `facade_alias`     | `string\|null`                  | `Likes`          | `LIKES_FACADE_ALIAS`      | Global class alias for the `Likes` facade. Set `null` to skip aliasing.                        |

## Usage

Add the `HasLikes` trait to models that can be liked, and the `GivesLikes` trait to models
that can give likes:

```php
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Likes\Traits\GivesLikes;
use RoundlyConsulting\Likes\Traits\HasLikes;

class Post extends Model
{
    use HasLikes;
}

class User extends Model
{
    use GivesLikes;
}
```

### Liking, unliking, toggling

All three verbs are idempotent and return a boolean meaning **"is-liked-now"** — so `like()`
returns `true`, `unlike()` returns `false`, and `toggleLike()` returns the resulting state:

```php
$post = Post::find(1);
$user = auth()->user();

$user->like($post);        // true  — ensure liked (no-op if already liked)
$user->unlike($post);      // false — ensure not liked (no-op if not liked)
$user->toggleLike($post);  // true on like, false on unlike
```

Re-liking restores a previously removed like rather than creating a duplicate row, so each
actor keeps a single record per likeable and reaction type.

### Checking state and counting

```php
$post->hasBeenLikedBy($user);  // bool
$post->isLikedBy($user);       // bool — readable alias of hasBeenLikedBy()
$user->hasLiked($post);        // bool

$post->likesCount();           // int — uses an eager-loaded count when present, else counts live
```

### Query scopes

```php
Post::orderByLikesDesc()->limit(10)->get();  // most-liked first (no N+1)
Post::orderByLikes()->get();                 // least-liked first
Post::whereLikedBy($user)->get();            // posts the user liked
Post::whereNotLikedBy($user)->get();         // posts the user has not liked
Post::withLikesCount()->get();               // hydrate a `likes_count` attribute
```

### Typed reactions (opt-in)

By default a single implicit `like` reaction is configured, so behaviour is unchanged. Add
more to `config/likes.php` to enable typed reactions:

```php
'reactions' => ['like', 'love', 'wow', 'laugh'],
'default_reaction' => 'like',
```

Then pass a type to any verb, check, count, or scope:

```php
$user->like($post, 'love');
$post->isLikedBy($user, 'love');
$post->likesCount('love');
Post::orderByLikesDesc('love')->get();
```

An unconfigured type throws `RoundlyConsulting\Likes\Exceptions\InvalidReactionTypeException`.

### The `Likes` facade

The facade resolves the actor from the authenticated user by default, so the common path is
one line. Override the actor or reaction type with the fluent builder:

```php
use RoundlyConsulting\Likes\Facades\Likes;

Likes::like($post);                     // as the authenticated user
Likes::toggle($post);
Likes::has($post);                      // bool

Likes::actor($user)->like($comment);    // as any actor
Likes::actor($team)->toggle($post);
Likes::as('love')->like($post);         // typed reaction
Likes::actor($user)->as('wow')->toggle($post);
```

If no actor is supplied and none can be resolved, a
`RoundlyConsulting\Likes\Exceptions\NoAuthenticatedActorException` is thrown. Configure a
custom resolver (for non-`web` guards or non-user actors) via `actor_resolver`:

```php
'actor_resolver' => fn () => auth('api')->user(),
// or an invokable class-string:
'actor_resolver' => \App\Likes\CurrentActorResolver::class,
```

### Bulk operations

```php
$user->likeMany([$post, $comment, $photo]);
$user->unlikeMany([$post, $comment]);

Likes::likeMany([$post, $comment]);     // as the resolved actor
```

### Blade directive

```blade
@liked($post)
    <x-icon name="heart-filled" />
@else
    <x-icon name="heart" />
@endliked
```

`@liked($model)` uses the authenticated actor; `@liked($model, $actor)` honours an explicit
actor; `@liked($model, $actor, 'love')` honours a reaction type.

### Events

Idempotent no-ops (liking what's already liked, unliking what isn't) dispatch **no** events.
On a real change the package fires the precise event plus the back-compatible `LikeToggled`:

```php
use RoundlyConsulting\Likes\Events\Liked;
use RoundlyConsulting\Likes\Events\Unliked;
use RoundlyConsulting\Likes\Events\LikeToggled;

class NotifyAuthor
{
    public function handle(Liked $event): void
    {
        // $event->actor, $event->likeable, $event->type, $event->like
    }
}
```

| Event         | When                | Properties                                       |
|---------------|---------------------|--------------------------------------------------|
| `Liked`       | a like is created   | `actor`, `likeable`, `type`, `like`              |
| `Unliked`     | a like is removed   | `actor`, `likeable`, `type`, `like`              |
| `LikeToggled` | either of the above | `actor`, `entity`, `hasBeenLiked` (back-compat)  |

#### Optional: a persisted counter column

This package does not add columns to your own tables. For O(1) counts you can use
`withLikesCount()` / `loadCount('likes')`, or maintain your own counter column with a small
listener — for example incrementing a `likes_count` column on the likeable:

```php
use RoundlyConsulting\Likes\Events\Liked;
use RoundlyConsulting\Likes\Events\Unliked;

class SyncLikesCounter
{
    public function increment(Liked $event): void
    {
        $event->likeable->increment('likes_count');
    }

    public function decrement(Unliked $event): void
    {
        $event->likeable->decrement('likes_count');
    }
}
```

Register it in your own `EventServiceProvider`; the package does not auto-register it.

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security
vulnerabilities.

## Credits

- [Andrej Mihaliak](https://github.com/mihaliak)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
