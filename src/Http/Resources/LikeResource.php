<?php

declare(strict_types=1);

namespace RoundlyConsulting\Likes\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use RoundlyConsulting\Likes\Contracts\Likeable;

/**
 * Wraps a likeable model and renders its like summary for API responses.
 * Delegates to HasLikes::toLikeArray() so the shape and counting logic stay in
 * one place.
 */
final class LikeResource extends JsonResource
{
    /**
     * @return array{count: int, viewer_state: array{liked: bool, reaction: ?string}, breakdown: array<string, int>}
     */
    public function toArray(Request $request): array
    {
        /** @var Likeable $likeable */
        $likeable = $this->resource;

        // No explicit viewer: the viewer resolves like every other read (`likes.actor_resolver`,
        // else the authenticated user). Passing `$request->user()` skipped a configured resolver.
        return $likeable->toLikeArray();
    }
}
