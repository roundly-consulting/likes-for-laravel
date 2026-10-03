<?php

declare(strict_types=1);

use RoundlyConsulting\Likes\Tests\Fixtures\PublishSandboxTestCase;

/**
 * Publishing runs against a throwaway config/ and database/ ({@see PublishSandboxTestCase}),
 * never the testbench skeleton the parallel suite boots from.
 */
it('publishes into the sandbox, never the shared skeleton', function (): void {
    expect(config_path('likes.php'))->toContain('likes-publish-')
        ->and(database_path('migrations'))->toContain('likes-publish-');
});

it('publishes the config file', function (): void {
    $target = config_path('likes.php');

    $this->artisan('vendor:publish', ['--tag' => 'likes-config'])->assertSuccessful();

    expect(file_exists($target))->toBeTrue();
});

it('publishes the migrations', function (): void {
    $this->artisan('vendor:publish', ['--tag' => 'likes-migrations'])->assertSuccessful();
});
