<?php

declare(strict_types=1);

namespace RoundlyConsulting\Likes\Tests\Models\Concerns;

use Illuminate\Database\Eloquent\Concerns\HasUniqueStringIds;
use Illuminate\Support\Str;
use RoundlyConsulting\PackageToolkit\Enums\KeyType;

/**
 * A fixture actor or likeable keyed the way `likes.key_type` says — an integer, a ULID or a
 * UUIDv7 — so the whole suite runs against hosts of every key type (LIKES_KEY_TYPE).
 */
trait FollowsLikesKeyType
{
    use HasUniqueStringIds;

    public function initializeHasUniqueStringIds(): void
    {
        $this->usesUniqueIds = KeyType::fromConfig('likes.key_type') !== KeyType::BigInt;
    }

    public function newUniqueId(): string
    {
        return KeyType::fromConfig('likes.key_type') === KeyType::Ulid ? strtolower((string) Str::ulid()) : (string) Str::uuid7();
    }

    protected function isValidUniqueId(mixed $value): bool
    {
        return KeyType::fromConfig('likes.key_type') === KeyType::Ulid ? Str::isUlid($value) : Str::isUuid($value);
    }
}
