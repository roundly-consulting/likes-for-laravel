<?php

declare(strict_types=1);

use RoundlyConsulting\Likes\DataTransferObjects\ReactionSummary;

it('returns zero for an absent type via countFor', function (): void {
    $summary = new ReactionSummary(['like' => 3], 3, 'like', null);

    expect($summary->countFor('like'))->toBe(3)
        ->and($summary->countFor('love'))->toBe(0);
});

it('reflects counts via has', function (): void {
    $summary = new ReactionSummary(['like' => 3, 'love' => 0], 3, 'like', null);

    expect($summary->has('like'))->toBeTrue()
        ->and($summary->has('love'))->toBeFalse()
        ->and($summary->has('wow'))->toBeFalse();
});

it('exposes a stable array shape', function (): void {
    $summary = new ReactionSummary(['like' => 8, 'love' => 4], 12, 'like', 'love');

    expect($summary->toArray())->toBe([
        'total' => 12,
        'counts' => ['like' => 8, 'love' => 4],
        'top' => 'like',
        'viewer' => 'love',
    ]);
});
