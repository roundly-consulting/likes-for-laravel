<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException;
use RoundlyConsulting\Testing\Database\DriverMatrix;

function runLikesMigration(): void
{
    Schema::dropIfExists((string) config('likes.table', 'likes'));

    $migration = require __DIR__.'/../../database/migrations/create_likes_table.php';
    $migration->up();
}

/**
 * The emitted `CREATE TABLE` statement — the sqlite catalog's verbatim record of the
 * columns, so a key-type or nullability regression cannot hide behind a column-existence
 * assertion. SQLite-only by construction.
 */
function likesCreateTable(string $table): string
{
    /** @var list<object{sql: string|null}> $rows */
    $rows = DB::select('select sql from sqlite_master where type = ? and name = ?', ['table', $table]);

    return (string) ($rows[0]->sql ?? '');
}

/**
 * The Postgres catalog's real type + nullability for a column — the only engine that can
 * tell bigint / uuid / char(26) apart, and the only place these cases can fail at all.
 *
 * @return array{type: string, nullable: string}
 */
function likesPgColumn(string $table, string $column): array
{
    /** @var list<object{data_type: string, character_maximum_length: int|null, is_nullable: string}> $rows */
    $rows = DB::select(
        'select data_type, character_maximum_length, is_nullable from information_schema.columns where table_name = ? and column_name = ?',
        [$table, $column],
    );

    $row = $rows[0] ?? null;

    if ($row === null) {
        return ['type' => 'MISSING', 'nullable' => 'MISSING'];
    }

    $type = $row->character_maximum_length === null
        ? $row->data_type
        : $row->data_type.'('.$row->character_maximum_length.')';

    return ['type' => $type, 'nullable' => $row->is_nullable];
}

$sqliteOnly = fn (): bool => DriverMatrix::driver() !== 'sqlite';
$pgsqlOnly = fn (): bool => DriverMatrix::driver() !== 'pgsql';

it('creates the polymorphic actor and likeable columns', function (): void {
    config()->set('likes.key_type', 'bigint');
    runLikesMigration();

    expect(Schema::hasColumns('likes', [
        'id', 'actor_type', 'actor_id', 'likeable_type', 'likeable_id', 'type',
        'created_at', 'updated_at', 'deleted_at',
    ]))->toBeTrue();
});

/**
 * The core safety property of the P1 sweep: `morphKey($n, BigInt, nullable: false)` IS
 * `morphs($n)`. Proven by diffing the migration's emitted columns against a table built
 * from raw `morphs()` — if the rename ever changed the default schema (wrong nullability,
 * wrong type, wrong order) the two CREATE TABLE strings would differ.
 */
it('emits a bigint morph schema byte-identical to raw morphs()', function (): void {
    config()->set('likes.key_type', 'bigint');
    config()->set('likes.table', 'likes');
    runLikesMigration();

    Schema::dropIfExists('likes_raw_ref');
    Schema::create('likes_raw_ref', function (Blueprint $table): void {
        $table->id();
        $table->morphs('actor');
        $table->morphs('likeable');
        $table->string('type')->default('like');
        $table->timestamps();
        $table->softDeletes();
    });

    $actual = likesCreateTable('likes');
    $reference = str_replace('"likes_raw_ref"', '"likes"', likesCreateTable('likes_raw_ref'));

    expect($actual)->toBe($reference);

    Schema::dropIfExists('likes_raw_ref');
})->skip($sqliteOnly, 'sqlite_master is the sqlite catalog');

/**
 * The headline of P1: a uuid/ulid host gets uuid/ulid morph id columns, and bigint stays
 * bigint (never `integer` — a 32-bit id silently caps the table at 2.1bn rows). Postgres
 * reports the three distinctly; SQLite calls them all `integer`/`varchar` and cannot bite.
 * Both morph ids are non-nullable, matching the raw `morphs()` they replaced.
 */
it('renders each configured key type as a distinct real column type', function (string $keyType, string $expected): void {
    config()->set('likes.key_type', $keyType);
    config()->set('likes.table', 'kt_likes');

    // Build the alternately-named table on its own; the unique index name derives from it.
    Schema::dropIfExists('likes');
    Schema::dropIfExists('kt_likes');
    $migration = require __DIR__.'/../../database/migrations/create_likes_table.php';
    $migration->up();

    expect(likesPgColumn('kt_likes', 'actor_id'))->toBe(['type' => $expected, 'nullable' => 'NO'])
        ->and(likesPgColumn('kt_likes', 'likeable_id'))->toBe(['type' => $expected, 'nullable' => 'NO'])
        ->and(likesPgColumn('kt_likes', 'actor_type')['type'])->toBe('character varying(255)');

    Schema::dropIfExists('kt_likes');
})->with([
    'bigint' => ['bigint', 'bigint'],
    'uuid' => ['uuid', 'uuid'],
    'ulid' => ['ulid', 'character(26)'],
])->skip($pgsqlOnly, 'needs the postgres catalog to tell the key types apart');

it('refuses to migrate on an unrecognized key type instead of falling back to bigint', function (): void {
    config()->set('likes.key_type', 'nonsense');
    config()->set('likes.table', 'fallback_likes');

    Schema::dropIfExists('likes');
    Schema::dropIfExists('fallback_likes');

    // A typo in a host's config must stop the migration, never silently build bigint
    // columns for a uuid/ulid-keyed host.
    expect(function (): void {
        $migration = require __DIR__.'/../../database/migrations/create_likes_table.php';
        $migration->up();
    })->toThrow(InvalidConfigurationException::class, 'Configuration value [likes.key_type] must be one of [bigint, uuid, ulid] (case-insensitive), [nonsense] given.');

    Schema::dropIfExists('fallback_likes');
});
