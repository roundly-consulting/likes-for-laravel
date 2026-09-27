# Changelog

All notable changes to `likes-for-laravel` are documented in this file. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses
[Semantic Versioning](https://semver.org/).

## Unreleased

Initial public release.

### Added

- Likes on any Eloquent model from any model: a `HasLikes` trait for likeables, a `GivesLikes`
  trait for actors, and one polymorphic `likes` table.
- Idempotent like, unlike and toggle, plus bulk `Likes::likeMany()`.
- Optional typed reactions (love, wow, …) configured in `likes.reactions`, and `react()` to switch
  an actor's reaction in place.
- Query scopes: `orderByLikesDesc()`, `whereLikedBy()`, `whereNotLikedBy()`, `withLikesCount()`.
- Single-query feed hydration with `withLikedState()` (`is_liked`, `liked_reaction`), guest-safe.
- Ranking by weighted reaction score (`orderByLikeScore()`) and by recent activity
  (`orderByTrending()`).
- `reactionSummary()` breakdowns and a reverse `likedItems()` relation for "what X liked".
- A `Likes` facade that resolves the authenticated actor, with a configurable actor resolver.
- A `@liked` Blade directive and a `LikeResource` JSON resource.
- `Liked`, `Unliked`, `ReactionChanged` and `LikeToggled` events, with opt-in broadcasting over
  Laravel Echo.
- `Likes::fake()` with assertions such as `assertLiked()`, `assertLikedBy()` and
  `assertNothingLiked()`.
