<?php

declare(strict_types=1);

use Illuminate\Http\Request;
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
