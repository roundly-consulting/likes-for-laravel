<?php

declare(strict_types=1);

use RoundlyConsulting\Likes\Exceptions\InvalidReactionTypeException;
use RoundlyConsulting\Likes\Exceptions\LikesException;
use RoundlyConsulting\Likes\Exceptions\NoAuthenticatedActorException;

it('extends the base LikesException', function (): void {
    expect(InvalidReactionTypeException::for('x', ['like']))->toBeInstanceOf(LikesException::class)
        ->and(NoAuthenticatedActorException::make())->toBeInstanceOf(LikesException::class);
});

it('describes the allowed reaction types', function (): void {
    $exception = InvalidReactionTypeException::for('angry', ['like', 'love']);

    expect($exception->getMessage())
        ->toContain('angry')
        ->toContain('like')
        ->toContain('love');
});
