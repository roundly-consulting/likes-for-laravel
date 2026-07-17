<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use RoundlyConsulting\PackageToolkit\Enums\KeyType;

return new class extends Migration
{
    public function up(): void
    {
        $table = config('likes.table', 'likes');

        // Silently falls back to bigint for an unrecognized value, so a typo in
        // the host's config never leaves the package unable to migrate.
        $keyType = KeyType::fromConfig('likes.key_type');

        /** @var string $table */
        Schema::create($table, function (Blueprint $table) use ($keyType): void {
            $table->id();
            $table->morphKey('actor', $keyType, nullable: false);
            $table->morphKey('likeable', $keyType, nullable: false);
            $table->string('type')->default('like');
            $table->timestamps();
            $table->softDeletes();

            $table->index(
                ['actor_type', 'actor_id', 'likeable_type', 'likeable_id', 'type'],
                'likes_actor_likeable_type_index',
            );
        });
    }
};
