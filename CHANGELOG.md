# Changelog

All notable changes to `likes-for-laravel` are documented in this file. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses
[Semantic Versioning](https://semver.org/).

## Unreleased

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
