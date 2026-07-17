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
     * Resolve the table from `likes.table`, which the config file has always promised
     * "both the migration and the model read".
     *
     * Only the migration did. Eloquent derived `likes` from the class name, so a host that
     * set `LIKES_TABLE=reactions` got its schema built as `reactions` while every relation
     * and every write went looking for `likes` — the package was simply broken on any
     * value but the default. The scopes in HasLikes/GivesLikes already read the key, so
     * the model was the last reader missing.
     *
     * An explicit `$table` on a subclass still wins, exactly as Eloquent intends.
     */
    public function getTable(): string
    {
        if (isset($this->table)) {
            return $this->table;
        }

        $table = config('likes.table', 'likes');

        return is_string($table) && $table !== '' ? $table : 'likes';
    }

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
