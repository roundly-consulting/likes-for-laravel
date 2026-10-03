<?php

declare(strict_types=1);

use RoundlyConsulting\Likes\Testing\LikeExpectations;
use RoundlyConsulting\Likes\Tests\Fixtures\PublishSandboxTestCase;
use RoundlyConsulting\Likes\Tests\Fixtures\RenamedTableTestCase;
use RoundlyConsulting\Likes\Tests\Fixtures\SwappedLikeTestCase;
use RoundlyConsulting\Likes\Tests\TestCase;

// Explicit paths, not `->in(__DIR__)`: the ModelSwap, TableSwap and Publish directories below
// need a different base case, and a blanket bind would claim them first. ArchTest.php is
// listed because `swappableModelsAreNotFinal` reads the `likes.model` config default and
// so needs the app booted — an arch file is not automatically test-cased.
uses(TestCase::class)->in('ArchTest.php', 'Feature', 'Unit');

// The model-swap proofs need `likes.model` pointed at the host subclass BEFORE the
// providers boot, so they run on their own base case in their own directory — Pest binds a
// test case per directory, not per file.
uses(SwappedLikeTestCase::class)->in('ModelSwap');

// Likewise `likes.table`: the migration reads it inside up(), so the rename has to land
// before anything boots to produce a genuinely differently-named schema.
uses(RenamedTableTestCase::class)->in('TableSwap');

// Publishing writes files: into a throwaway config/ and database/ set before boot, never
// the testbench skeleton every parallel process boots its config and migrations from.
uses(PublishSandboxTestCase::class)->in('Publish');

LikeExpectations::register();
