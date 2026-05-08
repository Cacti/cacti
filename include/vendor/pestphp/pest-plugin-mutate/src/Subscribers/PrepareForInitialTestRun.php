<?php

declare(strict_types=1);

namespace Pest\Mutate\Subscribers;

use NunoMaduro\Collision\Adapters\Phpunit\Printers\DefaultPrinter;
use Pest\Mutate\Contracts\MutationTestRunner;
use Pest\Support\Container;
use PHPUnit\Event\TestSuite\Loaded;
use PHPUnit\Event\TestSuite\LoadedSubscriber;

/**
 * @internal
 */
final class PrepareForInitialTestRun implements LoadedSubscriber
{
    /**
     * Runs the subscriber.
     */
    public function notify(Loaded $event): void
    {
        /** @var MutationTestRunner $mutationTestRunner */
        $mutationTestRunner = Container::getInstance()->get(MutationTestRunner::class);

        if ($mutationTestRunner->isEnabled()) {
            DefaultPrinter::compact(true);
        }
    }
}
