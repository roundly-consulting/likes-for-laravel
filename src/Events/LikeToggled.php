<?php

declare(strict_types=1);

namespace RoundlyConsulting\Likes\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class LikeToggled
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public Model $actor,
        public Model $entity,
        public bool $hasBeenLiked,
    ) {}
}
