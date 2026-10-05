<?php

declare(strict_types=1);

namespace RoundlyConsulting\Likes\Tests\Models;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Likes\Traits\GivesLikes;
use RoundlyConsulting\Likes\Traits\HasLikes;

/**
 * A model that both gives and receives likes (a member liking posts and being liked), with
 * the trait collision on `likes()` resolved in favour of the actor side.
 */
final class DualRoleGivesWinsTestModel extends Model
{
    use GivesLikes, HasLikes {
        GivesLikes::likes insteadof HasLikes;
    }

    public $table = 'actors';

    public $timestamps = false;

    protected $guarded = [];
}
