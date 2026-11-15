<?php

declare(strict_types=1);

namespace RoundlyConsulting\Likes\Concerns;

use Illuminate\Database\Eloquent\Concerns\HasUniqueStringIds;
use Illuminate\Support\Str;
use RoundlyConsulting\Likes\Support\LikesConfig;
use RoundlyConsulting\PackageToolkit\Enums\KeyType;

/**
 * The Like model's own key, as `likes.primary_key_type` says: an auto-incrementing bigint, or a
 * ULID / UUIDv7 the model generates itself. Both string ids are time-ordered, so ordering by id
 * stays oldest first.
 */
trait HasConfigurableKey
{
    use HasUniqueStringIds;

    public function initializeHasUniqueStringIds(): void
    {
        $this->usesUniqueIds = $this->configuredKeyType() !== KeyType::BigInt;
    }

    public function newUniqueId(): string
    {
        return $this->configuredKeyType() === KeyType::Ulid
            ? strtolower((string) Str::ulid())
            : (string) Str::uuid7();
    }

    public function configuredKeyType(): KeyType
    {
        return LikesConfig::primaryKeyType();
    }

    protected function isValidUniqueId(mixed $value): bool
    {
        return $this->configuredKeyType() === KeyType::Ulid
            ? Str::isUlid($value)
            : Str::isUuid($value);
    }
}
