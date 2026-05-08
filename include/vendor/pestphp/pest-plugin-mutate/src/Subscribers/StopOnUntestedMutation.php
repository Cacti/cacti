<?php

declare(strict_types=1);

namespace Pest\Mutate\Subscribers;

use Pest\Mutate\Contracts\MutationTestRunner;
use Pest\Mutate\Event\Events\Test\Outcome\Untested;
use Pest\Mutate\Event\Events\Test\Outcome\UntestedSubscriber;
use Pest\Mutate\Repositories\ConfigurationRepository;
use Pest\Support\Container;

/**
 * @internal
 */
final class StopOnUntestedMutation implements UntestedSubscriber
{
    public function notify(Untested $event): void
    {
        if (! Container::getInstance()->get(ConfigurationRepository::class) // @phpstan-ignore-line
            ->mergedConfiguration()
            ->stopOnUntested) {
            return;
        }

        Container::getInstance()->get(MutationTestRunner::class) // @phpstan-ignore-line
            ->stopExecution();
    }
}
