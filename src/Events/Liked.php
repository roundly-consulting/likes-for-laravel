<?php

declare(strict_types=1);

namespace RoundlyConsulting\Likes\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use RoundlyConsulting\Likes\Models\Like;

final class Liked
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public Model $actor,
        public Model $likeable,
        public string $type,
        public Like $like,
    ) {}
}
