<?php

declare(strict_types=1);

namespace RoundlyConsulting\Likes\DataTransferObjects;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Likes\Support\ReactionType;

final readonly class LikeData
{
    public string $type;

    public function __construct(
        public Model $actor,
        public Model $likeable,
        ?string $type = null,
    ) {
        $this->type = ReactionType::resolve($type);
    }
}
