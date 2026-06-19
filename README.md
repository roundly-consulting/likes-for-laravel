# Likes for Laravel

Lightweight Laravel package to handle likes on entities. Any Eloquent model can give
likes, any model can receive them, and a single polymorphic `likes` table records who
liked what. Toggling a like fires an event so your application can react.

## Requirements

- PHP `^8.4`
- Laravel `^11.0` or `^12.0`

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

The published config file (`config/likes.php`) exposes a single key:

```php
<?php

declare(strict_types=1);

use RoundlyConsulting\Likes\Models\Like;

return [
    // The Eloquent model used to store likes. Swap in your own model
    // (extending the package model) if you need custom behaviour.
    'model' => Like::class,
];
```

| Key     | Type           | Default       | Purpose                                  |
|---------|----------------|---------------|------------------------------------------|
| `model` | `class-string` | `Like::class` | Eloquent model used to persist each like |

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

### Toggling a like

`toggleLike()` likes the entity if the actor has not liked it yet, otherwise it removes the
like. It returns `true` when a like was created and `false` when it was removed:

```php
$post = Post::find(1);
$user = auth()->user();

$liked = $user->toggleLike($post); // true on like, false on unlike
```

### Reading likes

```php
$user->likes; // Eloquent collection of likes the user has given
$post->likes; // Eloquent collection of likes the post has received
```

### Checking like state

```php
$post->hasBeenLikedBy($user); // bool — has this user liked the post?
$user->hasLiked($post);       // bool — has this user liked the post?
```

### Reacting to the event

Every call to `toggleLike()` dispatches `RoundlyConsulting\Likes\Events\LikeToggled`. Listen
for it to run custom logic when a like is added or removed:

```php
use RoundlyConsulting\Likes\Events\LikeToggled;

class NotifyAuthor
{
    public function handle(LikeToggled $event): void
    {
        // $event->actor        — the model that toggled the like (e.g. the user)
        // $event->entity       — the liked/unliked model (e.g. the post)
        // $event->hasBeenLiked — true if liked, false if unliked
    }
}
```

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
