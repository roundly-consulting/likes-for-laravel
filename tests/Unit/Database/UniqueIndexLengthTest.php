<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\ColumnDefinition;
use Illuminate\Support\Facades\DB;

/**
 * InnoDB (MySQL 8, MariaDB) caps an index key at 3072 bytes, and a `utf8mb4` character column
 * reserves 4 bytes per character in it. The likes unique index spans both morph pairs plus
 * `type`; with three `varchar(255)` columns it came to 3076 bytes on bigint keys (3348 uuid,
 * 3268 ulid), and `php artisan migrate` died with error 1071 on every MySQL host.
 *
 * Only a MySQL engine enforces the cap, so this measures the declared shape instead: the
 * migration runs in `pretend` mode, the Blueprint it builds is captured, and the index's
 * columns are summed at their MySQL key width. It runs on every leg; the `test-mysql` CI leg
 * is the real-engine proof.
 */
function likesUniqueIndexBytes(string $keyType): array
{
    config()->set('likes.key_type', $keyType);

    $captured = null;

    app()->bind(Blueprint::class, function ($app, array $parameters) use (&$captured): Blueprint {
        return $captured = new Blueprint($parameters['connection'], $parameters['table'], $parameters['callback'] ?? null);
    });

    DB::connection()->pretend(function (): void {
        $migration = require __DIR__.'/../../../database/migrations/create_likes_table.php';
        $migration->up();
    });

    expect($captured)->toBeInstanceOf(Blueprint::class);

    $unique = collect($captured->getCommands())->firstWhere('name', 'unique');
    $columns = collect($captured->getColumns())->keyBy('name');

    $widths = [];

    foreach ($unique->columns as $name) {
        /** @var ColumnDefinition $column */
        $column = $columns[$name];

        $widths[$name] = match ($column->type) {
            'string', 'char' => 4 * (int) $column->length,
            'uuid' => 4 * 36, // char(36) on MySQL
            'bigInteger' => 8,
            default => throw new LogicException("No MySQL key width for column type [{$column->type}]."),
        };
    }

    return $widths;
}

it('keeps the unique index within the InnoDB 3072-byte key limit', function (string $keyType): void {
    $widths = likesUniqueIndexBytes($keyType);

    // Pinned to the five columns, so the sum can never pass over an empty or partial parse.
    expect(array_keys($widths))->toBe(['actor_type', 'actor_id', 'likeable_type', 'likeable_id', 'type'])
        ->and(array_sum($widths))->toBeLessThanOrEqual(3072);
})->with(['bigint', 'uuid', 'ulid']);
