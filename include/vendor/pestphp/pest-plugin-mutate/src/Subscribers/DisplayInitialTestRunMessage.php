<?php

declare(strict_types=1);

namespace Pest\Mutate\Subscribers;

use PHPUnit\Event\TestSuite\Loaded;
use PHPUnit\Event\TestSuite\LoadedSubscriber;

/**
 * @internal
 */
final class DisplayInitialTestRunMessage implements LoadedSubscriber
{
    /**
     * Runs the subscriber.
     */
    public function notify(Loaded $event): void
    {
        // ...
    }
}
