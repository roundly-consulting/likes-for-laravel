<!-- roundly-hero:start -->
<p align="center">
  <a href="https://roundly-consulting.com/open-source/docs/likes-for-laravel?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=likes-for-laravel">
    <img src="https://raw.githubusercontent.com/roundly-consulting/likes-for-laravel/main/art/hero.png" alt="Likes for Laravel — Roundly open source" width="100%">
  </a>
</p>
<!-- roundly-hero:end -->

<!-- roundly-badges:start -->
<p align="center">
  <a href="https://packagist.org/packages/roundly-consulting/likes-for-laravel"><img src="https://img.shields.io/packagist/v/roundly-consulting/likes-for-laravel?style=flat-square&label=release" alt="Latest release"></a>
  <a href="https://github.com/roundly-consulting/likes-for-laravel/actions/workflows/run-tests.yml"><img src="https://img.shields.io/github/actions/workflow/status/roundly-consulting/likes-for-laravel/run-tests.yml?branch=main&style=flat-square&label=tests" alt="Tests"></a>
  <a href="https://github.com/roundly-consulting/likes-for-laravel/actions/workflows/fix-php-code-style-issues.yml"><img src="https://img.shields.io/github/actions/workflow/status/roundly-consulting/likes-for-laravel/fix-php-code-style-issues.yml?branch=main&style=flat-square&label=code%20style" alt="Code style"></a>
  <a href="https://donate.stripe.com/dRmeVe8FX5PF1Qd9pXcEw00"><img src="https://img.shields.io/badge/donate-support%20our%20open%20source-F24E29?style=flat-square&logo=stripe&logoColor=white" alt="Donate"></a>
  <a href="https://www.patreon.com/cw/roundly"><img src="https://img.shields.io/badge/patreon-become%20a%20patron-F96854?style=flat-square&logo=patreon&logoColor=white" alt="Patreon"></a>
  <a href="https://roundly-consulting.com/support-us?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=likes-for-laravel#crypto"><img src="https://img.shields.io/badge/crypto-BTC%20%C2%B7%20ETH%20%C2%B7%20BNB%20%C2%B7%20SOL-F7931A?style=flat-square&logo=bitcoin&logoColor=white" alt="Crypto"></a>
</p>
<!-- roundly-badges:end -->

# Likes for Laravel

Likes and reactions for any Eloquent model: any model can give likes, any model can receive them,
and one polymorphic `likes` table records who liked what. Typed reactions, popularity and trending
scopes, single-query feed hydration and reaction breakdowns come on top.

## Installation

Requires PHP 8.4 and Laravel 12 or 13.

```bash
composer require roundly-consulting/likes-for-laravel
php artisan vendor:publish --tag="likes-migrations"
php artisan migrate
```

If your actors or likeable models have UUID/ULID keys, set `LIKES_KEY_TYPE` **before** migrating.

## Usage

Let models give and receive likes:

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

Like and read through the facade — the actor defaults to the authenticated user:

```php
use RoundlyConsulting\Likes\Facades\Likes;

Likes::like($post);                     // true — liked now; liking again is a no-op
Likes::toggle($comment);                // the resulting state
Likes::actor($team)->like($post);       // as any other actor

Likes::for($post)->count();             // int
Likes::for($post)->likedBy($user);      // bool
Likes::for($post)->summary();           // ReactionSummary: total, counts, top, viewer's reaction
```

Render a feed with the viewer's state in a single query:

```php
$posts = Post::query()
    ->withLikedState()                  // is_liked + liked_reaction for the viewer
    ->withLikesCount()                  // likes_count — still one query
    ->latest()
    ->paginate();
```

<!-- roundly-docs:start -->
## Documentation

The full documentation — configuration, every feature and its API, and testing — lives on our
website: **[roundly-consulting.com/open-source/docs/likes-for-laravel](https://roundly-consulting.com/open-source/docs/likes-for-laravel?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=likes-for-laravel)**

Release notes are in [CHANGELOG.md](CHANGELOG.md). To contribute, see the
[contributing guide](https://github.com/roundly-consulting/.github/blob/main/CONTRIBUTING.md).
<!-- roundly-docs:end -->

<!-- roundly-support:start -->
## Support our work

This package is free and open source, built and maintained by
[Roundly Consulting](https://roundly-consulting.com/open-source?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=likes-for-laravel).
If it saves you time, please consider supporting our open-source work — a one-time donation, a
monthly pledge on Patreon or a crypto donation helps fund maintenance, new features and new
packages.

<a href="https://donate.stripe.com/dRmeVe8FX5PF1Qd9pXcEw00"><img src="https://img.shields.io/badge/Donate-Support%20Roundly%20open%20source-F24E29?style=for-the-badge&logo=stripe&logoColor=white" alt="Donate to Roundly open source"></a>
<a href="https://www.patreon.com/cw/roundly"><img src="https://img.shields.io/badge/Patreon-Become%20a%20patron-F96854?style=for-the-badge&logo=patreon&logoColor=white" alt="Become a patron on Patreon"></a>
<a href="https://roundly-consulting.com/support-us?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=likes-for-laravel#crypto"><img src="https://img.shields.io/badge/Crypto-BTC%20%C2%B7%20ETH%20%C2%B7%20BNB%20%C2%B7%20SOL-F7931A?style=for-the-badge&logo=bitcoin&logoColor=white" alt="Donate crypto: BTC, ETH, BNB or SOL"></a>
<!-- roundly-support:end -->

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
