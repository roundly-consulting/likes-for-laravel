<?php

declare(strict_types=1);

namespace RoundlyConsulting\Likes\Tests\Models;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Likes\Traits\GivesLikes;

final class ActorTestModel extends Model
{
    use GivesLikes;

    public $table = 'actors';

    public $timestamps = false;

    protected $guarded = [];
}
