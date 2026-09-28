<?php

declare(strict_types=1);

namespace RoundlyConsulting\Likes\Support;

use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Grammar;

/**
 * A SQL fragment the host wrote in its own config — today only the per-driver trending
 * override from `likes.trending.driver_expressions`, documented as used verbatim. It is
 * trusted exactly as far as the host's config is; never build one from request input.
 *
 * Laravel's own raw helpers insist on a literal-string, which a config value can never be,
 * so the fragment travels as its own expression instead.
 *
 * @internal
 */
final readonly class HostSql implements Expression
{
    public function __construct(
        private string $sql,
    ) {}

    public function getValue(Grammar $grammar): string
    {
        return $this->sql;
    }
}
