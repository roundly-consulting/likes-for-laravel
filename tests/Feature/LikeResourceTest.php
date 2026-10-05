<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use RoundlyConsulting\Likes\Facades\Likes;
use RoundlyConsulting\Likes\Http\Resources\LikeResource;
use RoundlyConsulting\Likes\Tests\Models\ActorTestModel;
use RoundlyConsulting\Likes\Tests\Models\PostTestModel;

beforeEach(function (): void {
    config()->set('likes.reactions', ['like', 'love']);

    $this->post = PostTestModel::query()->create();
    $this->viewer = ActorTestModel::query()->create();
});

it('matches toLikeArray for the request user', function (): void {
    $this->viewer->like($this->post, 'love');

    // Authenticated, as a real request user is: the resource resolves its viewer like every
    // other read, not from a request user the auth guard does not know.
    $this->actingAs($this->viewer);
    $request = Request::create('/');
    $request->setUserResolver(fn (): ActorTestModel => $this->viewer);

    $resource = LikeResource::make($this->post)->toArray($request);

    expect($resource)->toBe($this->post->toLikeArray($this->viewer));
});

it('resolves a guest viewer from the request', function (): void {
    $this->viewer->like($this->post, 'like');

    $request = Request::create('/');

    $resource = LikeResource::make($this->post)->toArray($request);

    expect($resource['viewer_state'])->toBe(['liked' => false, 'reaction' => null])
        ->and($resource['count'])->toBe(1);
});

/**
 * The resource used to hand `$request->user()` to toLikeArray() as an explicit viewer, which
 * skipped `likes.actor_resolver`. With a resolver acting as a team, the team's own like read
 * back as "not liked" for its logged-in user — while has(), toLikeArray() and
 * withLikedState() in the same request all said it was.
 */
it('resolves the viewer through the configured actor resolver', function (): void {
    $team = ActorTestModel::query()->create();
    config()->set('likes.actor_resolver', fn (): ActorTestModel => $team);

    Likes::as('love')->like($this->post);

    $this->actingAs($this->viewer);
    $request = Request::create('/');
    $request->setUserResolver(fn (): ActorTestModel => $this->viewer);

    $resource = LikeResource::make($this->post)->toArray($request);

    expect($resource['viewer_state'])->toBe(['liked' => true, 'reaction' => 'love'])
        ->and($resource)->toBe($this->post->toLikeArray());
});
