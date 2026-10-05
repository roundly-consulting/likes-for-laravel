<?php

declare(strict_types=1);

namespace RoundlyConsulting\Likes\Tests\Models\Forum;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Likes\Traits\HasLikes;

/**
 * Shares its basename with {@see \RoundlyConsulting\Likes\Tests\Models\PostTestModel}, from
 * another namespace — the shape that put two models on one broadcast channel.
 */
final class PostTestModel extends Model
{
    use HasLikes;

    public $table = 'posts';

    public $timestamps = false;

    protected $guarded = [];
}
