<?php

declare(strict_types=1);

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Likes\Events\Liked;
use RoundlyConsulting\Likes\Events\Unliked;
use RoundlyConsulting\Likes\Models\Like;
use RoundlyConsulting\Likes\Tests\Models\ActorTestModel;
use RoundlyConsulting\Likes\Tests\Models\PostTestModel;
use RoundlyConsulting\Likes\Tests\Support\Interleave;
use RoundlyConsulting\Testing\Database\DriverMatrix;
use RoundlyConsulting\Testing\Fixtures\LockRecorder;
use RoundlyConsulting\Testing\Fixtures\LockRecordingGrammar;

/**
 * A like used to be select-then-insert with no unique index behind it, so a double-click,
 * a second tab or a client retry landing between the SELECT and the INSERT produced two
 * rows and two `Liked` events — and `unlike()` then removed only one of them, leaving the
 * item liked. Each case below parks a second request in exactly that window.
 */
beforeEach(function (): void {
    config()->set('likes.reactions', ['like', 'love']);

    $this->actor = ActorTestModel::query()->create();
    $this->post = PostTestModel::query()->create();

    $this->events = ['liked' => 0, 'unliked' => 0];
    Event::listen(Liked::class, function (): void {
        $this->events['liked']++;
    });
    Event::listen(Unliked::class, function (): void {
        $this->events['unliked']++;
    });
});

it('keeps one row and fires one Liked when two likes interleave', function (): void {
    Interleave::afterRead(fn () => $this->actor->like($this->post));

    $result = $this->actor->like($this->post);

    expect($result)->toBeTrue()
        ->and(Like::withTrashed()->count())->toBe(1)
        ->and($this->events['liked'])->toBe(1)
        ->and($this->post->likesCount())->toBe(1);
});

it('fires one Liked when two re-likes race to restore the same row', function (): void {
    $this->actor->like($this->post);
    $this->actor->unlike($this->post);
    $this->events = ['liked' => 0, 'unliked' => 0];

    Interleave::afterRead(fn () => $this->actor->like($this->post));

    $this->actor->like($this->post);

    expect(Like::withTrashed()->count())->toBe(1)
        ->and(Like::query()->count())->toBe(1)
        ->and($this->events['liked'])->toBe(1);
});

it('fires one Unliked when two unlikes interleave', function (): void {
    $this->actor->like($this->post);

    Interleave::afterRead(fn () => $this->actor->unlike($this->post));

    $result = $this->actor->unlike($this->post);

    expect($result)->toBeFalse()
        ->and(Like::query()->count())->toBe(0)
        ->and($this->events['unliked'])->toBe(1);
});

it('lands two racing toggles on one row with one Liked', function (): void {
    // The 2nd read is the like branch's own lookup, after toggle's exists() check.
    Interleave::afterRead(fn () => $this->actor->toggleLike($this->post), nth: 2);

    $result = $this->actor->toggleLike($this->post);

    expect($result)->toBeTrue()
        ->and(Like::withTrashed()->count())->toBe(1)
        ->and($this->events['liked'])->toBe(1)
        ->and($this->actor->hasLiked($this->post))->toBeTrue();
});

it('leaves the item unliked after a raced like and one unlike', function (): void {
    Interleave::afterRead(fn () => $this->actor->like($this->post));
    $this->actor->like($this->post);

    $this->actor->unlike($this->post);

    expect($this->actor->hasLiked($this->post))->toBeFalse()
        ->and($this->actor->toggleLike($this->post))->toBeTrue();
});

it('lets the database refuse a duplicate actor, likeable and type', function (): void {
    $row = [
        'actor_id' => $this->actor->getKey(),
        'actor_type' => $this->actor->getMorphClass(),
        'likeable_id' => $this->post->getKey(),
        'likeable_type' => $this->post->getMorphClass(),
        'type' => 'like',
    ];

    Like::query()->create($row)->delete();

    // Soft-deleted rows count too: a re-like restores, it never inserts a second row.
    Like::query()->create($row);
})->throws(UniqueConstraintViolationException::class);

it('still allows one row per reaction type', function (): void {
    $this->actor->like($this->post, 'like');
    $this->actor->like($this->post, 'love');

    expect(Like::query()->count())->toBe(2);
});

it('takes the restore and delete under a row lock inside a transaction', function (): void {
    $connection = DB::connection();
    $connection->setQueryGrammar(new LockRecordingGrammar($connection));
    LockRecorder::flush();
    LockRecorder::listenForMarkers();

    $this->actor->like($this->post);
    expect(LockRecorder::recorded())->toBe([]);

    $this->actor->unlike($this->post);
    $this->actor->like($this->post);

    expect(LockRecorder::recorded())->toHaveCount(2)
        ->and(array_column(LockRecorder::recorded(), 'marker'))->toBe(['lock-for-update', 'lock-for-update'])
        ->and(array_column(LockRecorder::recorded(), 'transactionDepth'))->toBe([1, 1]);
})->skip(fn (): bool => DriverMatrix::driver() !== 'sqlite', 'the lock-recording grammar is a SQLite grammar');
