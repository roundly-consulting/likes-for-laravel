<?php

declare(strict_types=1);

namespace RoundlyConsulting\Likes\Tests\Models;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Likes\Traits\HasLikes;

final class CommentTestModel extends Model
{
    use HasLikes;

    public $table = 'comments';

    public $timestamps = false;

    protected $guarded = [];
}
