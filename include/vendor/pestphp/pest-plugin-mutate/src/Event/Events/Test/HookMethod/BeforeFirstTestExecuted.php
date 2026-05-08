<?php

declare(strict_types=1);

namespace Pest\Mutate\Event\Events\Test\HookMethod;

use Pest\Mutate\Contracts\Event;
use Pest\Mutate\MutationTestCollection;

class BeforeFirstTestExecuted implements Event
{
    public function __construct(
        public readonly MutationTestCollection $testCollection,
    ) {}
}
