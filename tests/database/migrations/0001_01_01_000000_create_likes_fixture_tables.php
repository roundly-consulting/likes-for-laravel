<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

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
        Schema::create('actors', function (Blueprint $table): void {
            $table->increments('id');
        });

        Schema::create('posts', function (Blueprint $table): void {
            $table->increments('id');
        });

        Schema::create('comments', function (Blueprint $table): void {
            $table->increments('id');
        });
    }
};
