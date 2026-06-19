<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $table = config('likes.table', 'likes');

        /** @var string $table */
        Schema::create($table, function (Blueprint $table): void {
            $table->id();
            $table->morphs('actor');
            $table->morphs('likeable');
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
