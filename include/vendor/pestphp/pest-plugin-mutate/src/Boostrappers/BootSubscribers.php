<?php

declare(strict_types=1);

namespace Pest\Mutate\Boostrappers;

use Pest\Contracts\Bootstrapper;
use Pest\Mutate\Contracts\Subscriber;
use Pest\Mutate\Event\Facade;
use Pest\Mutate\Subscribers\StopOnUncoveredMutation;
use Pest\Mutate\Subscribers\StopOnUntestedMutation;
use Pest\Mutate\Subscribers\TrackMutationSuiteFinish;
use Pest\Mutate\Subscribers\TrackMutationSuiteStart;
use Pest\Support\Container;

/**
 * @internal
 */
final readonly class BootSubscribers implements Bootstrapper
{
    /**
     * The list of Subscribers.
     *
     * @var array<int, class-string<Subscriber>>
     */
    private const SUBSCRIBERS = [
        TrackMutationSuiteStart::class,
        TrackMutationSuiteFinish::class,
        StopOnUncoveredMutation::class,
        StopOnUntestedMutation::class,
    ];

    /**
     * Creates a new instance of the Boot Subscribers.
     */
    public function __construct(
        private Container $container,
    ) {}

    /**
     * Boots the list of Subscribers.
     */
    public function boot(): void
    {
        foreach (self::SUBSCRIBERS as $subscriber) {
            $instance = $this->container->get($subscriber);

            assert($instance instanceof Subscriber);

            Facade::instance()->registerSubscriber($instance);
        }
    }
}
