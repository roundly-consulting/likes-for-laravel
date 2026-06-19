<?php

declare(strict_types=1);

namespace RoundlyConsulting\Likes\Exceptions;

final class InvalidReactionTypeException extends LikesException
{
    /**
     * @param  list<string>  $allowed
     */
    public static function for(string $type, array $allowed): self
    {
        return new self(sprintf(
            'The reaction type [%s] is not allowed. Allowed types: [%s].',
            $type,
            implode(', ', $allowed),
        ));
    }
}
