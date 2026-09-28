<?php

declare(strict_types=1);

use RoundlyConsulting\Likes\Tests\Models\ActorTestModel;
use RoundlyConsulting\Likes\Tests\Models\PostTestModel;

/**
 * The secret-safe `about` capture (A).
 *
 * Purchases #13 is the bug this exists for: the fleet's most credential-heavy `about`
 * section was guarded by negative assertions against `app(Kernel::class)->output()`, which
 * returns `''`. Every "does not leak" check was vacuous — passing against empty output.
 *
 * Likes carries no credentials, which is exactly why it is worth being precise about what
 * the risk actually is here: it is **social graph**, not secrets. Who liked what is
 * personal data, and a broadcast channel prefix plus a host's actor class would describe
 * the private channels the package publishes on. The section must report configuration and
 * never data.
 */
it('renders the likes section without leaking the social graph it stores', function (): void {
    config()->set('likes.reactions', ['like', 'love', 'wow']);
    config()->set('likes.broadcast.enabled', true);

    $actor = ActorTestModel::query()->create();
    $post = PostTestModel::query()->create();
    $actor->like($post);

    expect('likes')->toLeakNoSecrets(
        secrets: [
            // No host class, no id, and no row from the likes table ever renders: the
            // section describes configuration, not who liked what.
            ActorTestModel::class,
            PostTestModel::class,
        ],
        mustRender: [
            // The positive proof each line reports rather than silently rendering empty.
            'Reactions',
            'like, love, wow',
            'Default reaction',
            'Broadcasting',
            'ENABLED',
        ],
    );
});

/**
 * The other branch of the broadcasting line. Without this, the assertion above could pass
 * against a section that only ever renders when broadcasting is on.
 */
it('reports broadcasting off by default', function (): void {
    expect('likes')->toLeakNoSecrets(
        secrets: [ActorTestModel::class],
        mustRender: ['Broadcasting', 'OFF'],
    );
});

it('reports broadcasting enabled from an env-string flag', function (): void {
    // LIKES_BROADCAST=1 reaches config as the string "1"; the events broadcast on it, so
    // `about` must not claim OFF.
    config()->set('likes.broadcast.enabled', '1');

    expect('likes')->toLeakNoSecrets(
        secrets: [ActorTestModel::class],
        mustRender: ['Broadcasting', 'ENABLED'],
    );
});
