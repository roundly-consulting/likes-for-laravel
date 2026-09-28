<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Likes\Events\Liked;
use RoundlyConsulting\Likes\Events\ReactionChanged;
use RoundlyConsulting\Likes\Events\Unliked;
use RoundlyConsulting\Likes\Models\Like;
use RoundlyConsulting\Likes\Tests\Models\ActorTestModel;
use RoundlyConsulting\Likes\Tests\Models\PostTestModel;
use RoundlyConsulting\Likes\Tests\Support\Interleave;

/**
 * react() promises one active reaction per actor + likeable. With several reactions active
 * (the documented like('love') + like('wow') mode) it used to switch only the newest row in
 * place, leaving two active rows — and two of the same type when the target already
 * existed. Each case records the events so the accounting stays exact too.
 */
beforeEach(function (): void {
    config()->set('likes.reactions', ['like', 'love', 'wow']);

    $this->actor = ActorTestModel::query()->create();
    $this->post = PostTestModel::query()->create();

    $this->events = [];
    Event::listen(Liked::class, function (Liked $e): void {
        $this->events[] = 'liked:'.$e->type;
    });
    Event::listen(Unliked::class, function (Unliked $e): void {
        $this->events[] = 'unliked:'.$e->type;
    });
    Event::listen(ReactionChanged::class, function (ReactionChanged $e): void {
        $this->events[] = 'changed:'.$e->from.'>'.$e->to;
    });
});

function activeTypes(): array
{
    return Like::query()->orderBy('type')->pluck('type')->all();
}

it('collapses several active reactions onto the one that is already active', function (): void {
    $this->actor->like($this->post, 'love');
    $this->actor->like($this->post, 'wow');
    $this->events = [];

    expect($this->actor->react($this->post, 'love'))->toBeTrue()
        ->and(activeTypes())->toBe(['love'])
        ->and($this->post->reactionSummary()->counts)->toBe(['love' => 1])
        ->and($this->events)->toBe(['unliked:wow']);
});

it('switches the newest active reaction and drops the others', function (): void {
    $this->actor->like($this->post, 'love');
    $this->actor->like($this->post, 'wow');
    $this->events = [];

    $this->actor->react($this->post, 'like');

    expect(activeTypes())->toBe(['like'])
        ->and(Like::withTrashed()->count())->toBe(2)
        ->and($this->events)->toBe(['changed:wow>like', 'unliked:love']);
});

it('restores the target type row instead of colliding with it', function (): void {
    $this->actor->like($this->post, 'like');
    $this->actor->unlike($this->post, 'like');
    $this->actor->like($this->post, 'love');
    $this->events = [];

    $this->actor->react($this->post, 'like');

    expect(activeTypes())->toBe(['like'])
        ->and(Like::withTrashed()->where('type', 'like')->count())->toBe(1)
        ->and(Like::withTrashed()->count())->toBe(2)
        ->and($this->events)->toBe(['changed:love>like']);
});

it('adopts a target row that a concurrent request inserted mid-switch', function (): void {
    $this->actor->react($this->post, 'like');
    $this->events = [];

    // A concurrent like('love') lands right after react() locked the rows it saw (its 2nd
    // read; the 1st is the any-rows check) and before it retypes like → love: the unique
    // index refuses the retype, and react() must adopt the new row rather than fail.
    Interleave::afterRead(fn () => $this->actor->like($this->post, 'love'), nth: 2);

    expect($this->actor->react($this->post, 'love'))->toBeTrue()
        ->and(activeTypes())->toBe(['love'])
        ->and(Like::withTrashed()->where('type', 'love')->count())->toBe(1)
        ->and($this->events)->toBe(['liked:love', 'unliked:like']);
});

it('fires one ReactionChanged when two identical switches interleave', function (): void {
    $this->actor->react($this->post, 'like');
    $this->events = [];

    Interleave::afterRead(fn () => $this->actor->react($this->post, 'love'));

    $this->actor->react($this->post, 'love');

    expect(activeTypes())->toBe(['love'])
        ->and($this->events)->toBe(['changed:like>love']);
});

it('ends with one reaction when two different first reactions interleave', function (): void {
    Interleave::afterRead(fn () => $this->actor->react($this->post, 'wow'));

    $this->actor->react($this->post, 'love');

    expect(activeTypes())->toBe(['love'])
        ->and($this->events)->toBe(['liked:wow', 'liked:love', 'unliked:wow']);
});

it('reuses the target row when only removed reactions remain', function (): void {
    $this->actor->like($this->post, 'love');
    $this->actor->like($this->post, 'wow');
    $this->actor->unlike($this->post, 'love');
    $this->actor->unlike($this->post, 'wow');
    $this->events = [];

    $this->actor->react($this->post, 'love');

    expect(activeTypes())->toBe(['love'])
        ->and(Like::withTrashed()->count())->toBe(2)
        ->and($this->events)->toBe(['liked:love']);
});

it('adopts a concurrently inserted target when only removed reactions remain', function (): void {
    $this->actor->like($this->post, 'like');
    $this->actor->unlike($this->post, 'like');
    $this->events = [];

    // The removed `like` row would be reused and retyped to love — but a concurrent
    // like('love') inserts that row right after react() locked what it saw.
    Interleave::afterRead(fn () => $this->actor->like($this->post, 'love'), nth: 2);

    $this->actor->react($this->post, 'love');

    expect(activeTypes())->toBe(['love'])
        ->and(Like::withTrashed()->count())->toBe(2)
        ->and($this->events)->toBe(['liked:love']);
});

it('starts afresh when every row vanished before the lock', function (): void {
    $this->actor->like($this->post, 'like');
    $this->actor->unlike($this->post, 'like');
    $this->events = [];

    // A host job force-deletes the actor's history between react()'s check and its lock.
    Interleave::afterRead(fn () => Like::withTrashed()->forceDelete());

    $this->actor->react($this->post, 'love');

    expect(activeTypes())->toBe(['love'])
        ->and($this->events)->toBe(['liked:love']);
});
