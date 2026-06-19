<?php

declare(strict_types=1);

namespace RoundlyConsulting\Likes\Tests\Models;

use Illuminate\Database\Eloquent\Model;

final class CurrentActorResolver
{
    public function __invoke(): ?Model
    {
        return ActorTestModel::query()->first();
    }
}
