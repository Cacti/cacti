<?php

declare(strict_types=1);

namespace Pest\Mutate\Subscribers;

use Pest\Mutate\Event\Events\TestSuite\StartMutationSuite;
use Pest\Mutate\Event\Events\TestSuite\StartMutationSuiteSubscriber;

/**
 * @internal
 */
final class TrackMutationSuiteStart implements StartMutationSuiteSubscriber
{
    public function notify(StartMutationSuite $event): void
    {
        $event->mutationSuite->trackStart();
    }
}
