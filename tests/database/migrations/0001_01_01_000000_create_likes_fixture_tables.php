<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use RoundlyConsulting\PackageToolkit\Enums\KeyType;

/**
 * The host-owned tables the suite's actors and likeables live in. They belong to the
 * fixture, not to the package — likes hangs off unconstrained `morphs('actor')` and
 * `morphs('likeable')` precisely so a host's entities can live in any table.
 *
 * No `down()`: forward-only is the standard, and the real-engine reset drops every table
 * and re-migrates rather than rolling back.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Keyed like the host the suite pretends to be (`likes.key_type`, LIKES_KEY_TYPE).
        $id = static fn (Blueprint $table) => match (KeyType::fromConfig('likes.key_type')) {
            KeyType::BigInt => $table->increments('id'),
            KeyType::Ulid => $table->ulid('id')->primary(),
            KeyType::Uuid => $table->uuid('id')->primary(),
        };

        Schema::create('actors', function (Blueprint $table) use ($id): void {
            $id($table);
        });

        Schema::create('posts', function (Blueprint $table) use ($id): void {
            $id($table);
        });

        Schema::create('comments', function (Blueprint $table) use ($id): void {
            $id($table);
        });
    }
};
