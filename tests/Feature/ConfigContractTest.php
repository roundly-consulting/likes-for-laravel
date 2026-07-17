<?php

declare(strict_types=1);

/**
 * The config contract likes never had, pinned in both directions:
 *
 *  - forward — every key the code reads is shipped. This is shops #18, whose whole
 *    store-credit feature read `shops.payments.*` while the file shipped `payment.*`;
 *    330 tests stayed green because the suite set the same wrong key.
 *  - reverse — every shipped leaf is read. A documented key nothing reads is dead config
 *    that lies to the host: media #27's `max_file_size` cap that never applied, alerts
 *    #24's thrice-documented `escalation` key.
 *
 * Worth recording what this contract does *not* catch, because this row found it the hard
 * way: `likes.table` passed both directions the whole time — three readers, all real —
 * while the model, the one reader that mattered most, was missing. "Somebody reads it" is
 * a weaker claim than "it works", which is why TableSwap/RenamedTableTest.php exists.
 */
it('ships exactly the config keys it reads', function (): void {
    expect(__DIR__.'/../../config/likes.php')->toSatisfyConfigContract(__DIR__.'/../../src', [
        // Two keys are read through toolkit seams rather than `config()` tokens, and both
        // are real reads: `likes.model` via `ModelResolver::for('likes.model', …)`, which
        // drives the whole model swap, and `likes.facade_alias` via the provider's
        // `hasFacadeAlias(Likes::class, 'likes.facade_alias')`. The prefix is what makes
        // them visible to the scraper.
        'extraReadPrefixes' => ['likes.'],

        // Deliberately NO `excludeFromReverse` for the provider. The testing README's own
        // example excludes the service provider on the grounds that "a render is not a
        // read" — but LikesServiceProvider::contributesToAbout() calls
        // config('likes.broadcast.enabled') for real inside the closure it renders from,
        // and the provider is the only reader of `likes.facade_alias`. Excluding it would
        // discard readers and weaken the reverse direction for nothing.
    ]);
});
