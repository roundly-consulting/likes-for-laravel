<?php

declare(strict_types=1);

namespace RoundlyConsulting\Likes\Tests\Models;

use Illuminate\Auth\Authenticatable as AuthenticatableTrait;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\Access\Authorizable;
use RoundlyConsulting\Likes\Traits\GivesLikes;

final class ActorTestModel extends Model implements Authenticatable
{
    use AuthenticatableTrait;
    use Authorizable;
    use GivesLikes;

    public $table = 'actors';

    public $timestamps = false;

    protected $guarded = [];
}
