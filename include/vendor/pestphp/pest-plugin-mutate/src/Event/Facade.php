<?php

declare(strict_types=1);

namespace Pest\Mutate\Event;

use Pest\Mutate\Contracts\Subscriber;
use ReflectionClass;

class Facade
{
    private static ?self $instance = null;

    /**
     * @var array<class-string<Subscriber>, array<int, Subscriber>>
     */
    private array $subscribers = [];

    public static function instance(): self
    {
        if (! isset(self::$instance)) {
            self::$instance = new self;
        }

        return self::$instance;
    }

    /**
     * @return array<class-string<Subscriber>, array<int, Subscriber>>
     */
    public function subscribers(): array
    {
        return $this->subscribers;
    }

    public function emitter(): Emitter
    {
        return Emitter::instance();
    }

    public function registerSubscriber(Subscriber $subscriber): void
    {
        $reflection = new ReflectionClass($subscriber);

        $this->subscribers[$reflection->getInterfaceNames()[0]][] = $subscriber; // @phpstan-ignore-line
    }

    public function registerSubscribers(Subscriber ...$subscribers): void
    {
        foreach ($subscribers as $subscriber) {
            $this->registerSubscriber($subscriber);
        }
    }
}
