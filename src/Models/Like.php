<?php

declare(strict_types=1);

namespace RoundlyConsulting\Likes\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use RoundlyConsulting\Likes\Database\Factories\LikeFactory;

/**
 * @property int $id
 * @property int $actor_id
 * @property string $actor_type
 * @property int $likeable_id
 * @property string $likeable_type
 * @property string $type
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property CarbonInterface|null $deleted_at
 */
class Like extends Model
{
    /** @use HasFactory<LikeFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $guarded = [];

    /**
     * @return MorphTo<Model, $this>
     */
    public function actor(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function likeable(): MorphTo
    {
        return $this->morphTo();
    }

    protected static function newFactory(): LikeFactory
    {
        return LikeFactory::new();
    }
}
