<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;
use RoundlyConsulting\Likes\Tests\Models\ActorTestModel;
use RoundlyConsulting\Likes\Tests\Models\PostTestModel;

beforeEach(function (): void {
    $this->actor = ActorTestModel::create();
    $this->post = PostTestModel::create();
});

function renderLiked(PostTestModel $post, ?ActorTestModel $actor = null): string
{
    $template = $actor === null
        ? '@liked($post) liked @else not @endliked'
        : '@liked($post, $actor) liked @else not @endliked';

    return trim(Blade::render($template, ['post' => $post, 'actor' => $actor]));
}

it('renders the truthy branch for an explicit liked actor', function (): void {
    $this->actor->like($this->post);

    expect(renderLiked($this->post, $this->actor))->toBe('liked');
});

it('renders the falsy branch when the explicit actor has not liked', function (): void {
    expect(renderLiked($this->post, $this->actor))->toBe('not');
});

it('honours the authenticated actor when none is given', function (): void {
    $this->actingAs($this->actor);
    $this->actor->like($this->post);

    expect(renderLiked($this->post))->toBe('liked');
});

it('honours a reaction type as the final argument', function (): void {
    config()->set('likes.reactions', ['like', 'love']);
    $this->actor->like($this->post, 'love');

    $template = '@liked($post, $actor, "love") liked @else not @endliked';
    $output = trim(Blade::render($template, ['post' => $this->post, 'actor' => $this->actor]));

    expect($output)->toBe('liked');
});

/**
 * A guest has no actor, and `@liked` used to ask the builder anyway: it threw
 * NoAuthenticatedActorException and the whole view 500'd for every logged-out visitor. A
 * guest has liked nothing, so the directive renders the @else branch.
 */
it('renders the falsy branch for a guest instead of throwing', function (): void {
    $this->actor->like($this->post);

    expect(renderLiked($this->post))->toBe('not')
        ->and(trim(Blade::render('@liked($post, $actor) liked @else not @endliked', ['post' => $this->post, 'actor' => null])))
        ->toBe('not');
});

it('asks the configured actor resolver when no actor is given', function (): void {
    config()->set('likes.actor_resolver', fn (): ActorTestModel => $this->actor);
    $this->actor->like($this->post);

    expect(renderLiked($this->post))->toBe('liked');
});
