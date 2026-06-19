<?php

declare(strict_types=1);

namespace RoundlyConsulting\Likes\DataTransferObjects;

/**
 * Aggregate breakdown of the reactions on a single likeable: per-type counts,
 * a total, the most-used ("top") type, and the current viewer's reaction.
 */
final readonly class ReactionSummary
{
    /**
     * @param  array<string, int>  $counts
     */
    public function __construct(
        public array $counts,
        public int $total,
        public ?string $top,
        public ?string $viewerReaction,
    ) {}

    public function countFor(string $type): int
    {
        return $this->counts[$type] ?? 0;
    }

    public function has(string $type): bool
    {
        return $this->countFor($type) > 0;
    }

    /**
     * @return array{total: int, counts: array<string, int>, top: ?string, viewer: ?string}
     */
    public function toArray(): array
    {
        return [
            'total' => $this->total,
            'counts' => $this->counts,
            'top' => $this->top,
            'viewer' => $this->viewerReaction,
        ];
    }
}
