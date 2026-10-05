<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use RoundlyConsulting\Likes\Support\LikesConfig;
use RoundlyConsulting\PackageToolkit\Enums\KeyType;

return new class extends Migration
{
    public function up(): void
    {
        $name = LikesConfig::table();

        // Throws for an unrecognized value, so a typo in the host's config fails
        // the migration instead of quietly building bigint columns.
        $keyType = KeyType::fromConfig('likes.key_type');

        Schema::create($name, function (Blueprint $table) use ($name, $keyType): void {
            $table->id();
            $table->morphKey('actor', $keyType, nullable: false);
            $table->morphKey('likeable', $keyType, nullable: false);
            // 64, not 255: the unique index below spans two morph types plus this column, and
            // at varchar(255) it is over InnoDB's 3072-byte key limit under utf8mb4.
            $table->string('type', 64)->default('like');
            $table->timestamps();
            $table->softDeletes();

            // One row per actor + likeable + reaction type, soft-deleted rows included: a
            // re-like restores the old row instead of inserting. The database enforces it, so
            // a double-click or a retried request can never create a duplicate like.
            $table->unique(
                ['actor_type', 'actor_id', 'likeable_type', 'likeable_id', 'type'],
                $name.'_actor_likeable_type_unique',
            );
        });
    }
};
