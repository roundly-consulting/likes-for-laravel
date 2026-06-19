<?php

declare(strict_types=1);

namespace RoundlyConsulting\Likes\Exceptions;

final class NoAuthenticatedActorException extends LikesException
{
    public static function make(): self
    {
        return new self(
            'No actor could be resolved. Authenticate a user or pass an actor explicitly via Likes::actor($model).',
        );
    }
}
