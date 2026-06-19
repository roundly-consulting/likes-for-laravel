<?php

declare(strict_types=1);

namespace RoundlyConsulting\Likes\Tests\Models;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Likes\Contracts\Likeable;
use RoundlyConsulting\Likes\Traits\HasLikes;

final class PostTestModel extends Model implements Likeable
{
    use HasLikes;

    public $table = 'posts';

    public $timestamps = false;

    protected $guarded = [];
}
