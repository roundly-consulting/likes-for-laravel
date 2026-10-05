<?php

declare(strict_types=1);

namespace RoundlyConsulting\Likes\Tests\Models;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Likes\Traits\GivesLikes;
use RoundlyConsulting\Likes\Traits\HasLikes;

/**
 * A model that both gives and receives likes, with the trait collision on `likes()` resolved
 * in favour of the likeable side.
 */
final class DualRoleHasWinsTestModel extends Model
{
    use GivesLikes, HasLikes {
        HasLikes::likes insteadof GivesLikes;
    }

    public $table = 'actors';

    public $timestamps = false;

    protected $guarded = [];
}
