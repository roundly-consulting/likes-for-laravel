# Changelog

All notable changes to `likes-for-laravel` are documented in this file. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses
[Semantic Versioning](https://semver.org/).

## Unreleased

### Added

- `likesReceived()` on `HasLikes` and `likesGiven()` on `GivesLikes`: unambiguous relations for
  a model that both gives and receives likes. `likes()` stays on both traits.

### Changed

- Maintenance: CI also runs the suite on MySQL 8.

### Fixed

- The likes migration now runs on MySQL and MariaDB. The `type` column is `varchar(64)`
  (was 255), which keeps the unique index under InnoDB's 3072-byte key limit; `migrate`
  used to fail with error 1071. Reaction type names are now limited to 64 characters.
  Existing PostgreSQL and SQLite installs need no change.
- The per-type counts of `reactionSummary()`, `Likes::for($model)->summary()` and the
  `breakdown` of `toLikeArray()` / `LikeResource` come back ordered by type on every database.
  MySQL returned them in index order.
- `@liked($post)` renders the `@else` branch for a guest (and for a `null` actor) instead of
  throwing `NoAuthenticatedActorException`, which broke the whole view for logged-out visitors.
- A model using both `HasLikes` and `GivesLikes` (with `GivesLikes::likes insteadof HasLikes`)
  no longer counts the likes it gave in `withLikesCount()`, `orderByLikes()`,
  `orderByLikesDesc()`, `whereLikedBy()` and `whereNotLikedBy()`; the scopes now always use the
  received side.
- `LikeResource` resolves its viewer through `likes.actor_resolver` like every other read. It
  used to pass `$request->user()`, so with a resolver acting as another model (a team) the
  resource reported `liked: false` for that model's own like.
- With `likes.model` set to a subclass that names its own `$table`, `withLikedState()`,
  `orderByLikeScore()`, `orderByTrending()`, `likesOf()` and `likedItems()` read that table.
  They used to read `likes.table` and found no likes (or failed when that table did not exist).

## 1.0.0 - 2026-10-03

Initial public release.

### Added

- Likes on any Eloquent model from any model: a `HasLikes` trait for likeables, a `GivesLikes`
  trait for actors, and one polymorphic `likes` table.
- Idempotent, race-safe like, unlike and toggle — one row per actor, likeable and reaction
  type, enforced by a unique index — plus bulk `Likes::likeMany()`.
- Optional typed reactions (love, wow, …) configured in `likes.reactions`, and `react()` to switch
  an actor's reaction (collapsing several active reactions to one).
- Query scopes: `orderByLikesDesc()`, `whereLikedBy()`, `whereNotLikedBy()`, `withLikesCount()`
  (a typed count lands in its own `likes_{type}_count` attribute).
- Single-query feed hydration with `withLikedState()` (`is_liked`, `liked_reaction`), guest-safe.
- Ranking by weighted reaction score (`orderByLikeScore()`) and by recent activity
  (`orderByTrending()`), with optional per-driver raw trending expressions.
- `reactionSummary()` breakdowns and a reverse `likedItems()` relation for "what X liked".
- A `Likes` facade that resolves the authenticated actor, with a configurable actor resolver,
  and `Likes::for($likeable)` for reads: `count(?type)`, `summary(?viewer)`,
  `likedBy($actor, ?type)`. The `HasLikes` / `GivesLikes` traits delegate to the same manager.
- A `@liked` Blade directive and a `LikeResource` JSON resource.
- `Liked`, `Unliked` and `ReactionChanged` events, with opt-in broadcasting over
  Laravel Echo (the payload carries ids and the reaction only).
- `Likes::fake()` (a real static on the facade; also swaps injected `LikeManager`s) recording
  every completed write — facade, builder, bulk (per model), `GivesLikes` and `InteractsWithLikes` — with
  `assertLiked()`, `assertLikedBy()`, `assertNotLiked()`, `assertLikedCount()`,
  `assertLikedTimes()`, `assertUnliked()`, `assertReacted()` and `assertNothing{Liked,Unliked,Reacted}()`.
