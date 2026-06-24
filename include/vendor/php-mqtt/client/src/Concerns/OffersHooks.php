<?php

declare(strict_types=1);

namespace PhpMqtt\Client\Concerns;

use PhpMqtt\Client\Contracts\MqttClient;

/**
 * Contains common methods and properties necessary to offer hooks.
 *
 * @mixin MqttClient
 * @package PhpMqtt\Client\Concerns
 */
trait OffersHooks
{
    /** @var \SplObjectStorage|array<\Closure> */
    private $loopEventHandlers;

    /** @var \SplObjectStorage|array<\Closure> */
    private $publishEventHandlers;

    /** @var \SplObjectStorage|array<\Closure> */
    private $messageReceivedEventHandlers;

    /** @var \SplObjectStorage|array<\Closure> */
    private $connectedEventHandlers;

    /**
     * Needs to be called in order to initialize the trait.
     */
    protected function initializeEventHandlers(): void
    {
        $this->loopEventHandlers            = new \SplObjectStorage();
        $this->publishEventHandlers         = new \SplObjectStorage();
        $this->messageReceivedEventHandlers = new \SplObjectStorage();
        $this->connectedEventHandlers       = new \SplObjectStorage();
    }

    /**
     * Registers a loop event handler which is called each iteration of the loop.
     * This event handler can be used for example to interrupt the loop under
     * certain conditions.
     *
     * The loop event handler is passed the MQTT client instance as first and
     * the elapsed time which the loop is already running for as second
     * parameter. The elapsed time is a float containing seconds.
     *
     * Example:
     * ```php
     * $mqtt->registerLoopEventHandler(function (
     *     MqttClient $mqtt,
     *     float $elapsedTime
     * ) use ($logger) {
     *     $logger->info("Running for [{$elapsedTime}] seconds already.");
     * });
     * ```
     *
     * Multiple event handlers can be registered at the same time.
     */
    public function registerLoopEventHandler(\Closure $callback): MqttClient
    {
        $this->loopEventHandlers->offsetSet($callback);

        /** @var MqttClient $this */
        return $this;
    }

    /**
     * Unregisters a loop event handler which prevents it from being called
     * in the future.
     *
     * This does not affect other registered event handlers. It is possible
     * to unregister all registered event handlers by passing null as callback.
     */
    public function unregisterLoopEventHandler(?\Closure $callback = null): MqttClient
    {
        if ($callback === null) {
            $this->loopEventHandlers->removeAll($this->loopEventHandlers);
        } else {
            $this->loopEventHandlers->offsetUnset($callback);
        }

        /** @var MqttClient $this */
        return $this;
    }

    /**
     * Runs all registered loop event handlers with the given parameters.
     * Each event handler is executed in a try-catch block to avoid spilling exceptions.
     */
    private function runLoopEventHandlers(float $elapsedTime): void
    {
        foreach ($this->loopEventHandlers as $handler) {
            try {
                call_user_func($handler, $this, $elapsedTime);
            } catch (\Throwable $e) {
                $this->logger->error('Loop hook callback threw exception.', ['exception' => $e]);
            }
        }
    }

    /**
     * Registers a loop event handler which is called when a message is published.
     *
     * The loop event handler is passed the MQTT client as first, the topic as
     * second and the message as third parameter. As fourth parameter, the message identifier
     * will be passed, which can be null in case of QoS 0. The QoS level as well as the retained
     * flag will also be passed as fifth and sixth parameters.
     *
     * Example:
     * ```php
     * $mqtt->registerPublishEventHandler(function (
     *     MqttClient $mqtt,
     *     string $topic,
     *     string $message,
     *     ?int $messageId,
     *     int $qualityOfService,
     *     bool $retain
     * ) use ($logger) {
     *     $logger->info("Sending message on topic [{$topic}]: {$message}");
     * });
     * ```
     *
     * Multiple event handlers can be registered at the same time.
     */
    public function registerPublishEventHandler(\Closure $callback): MqttClient
    {
        $this->publishEventHandlers->offsetSet($callback);

        /** @var MqttClient $this */
        return $this;
    }

    /**
     * Unregisters a publish event handler which prevents it from being called
     * in the future.
     *
     * This does not affect other registered event handlers. It is possible
     * to unregister all registered event handlers by passing null as callback.
     */
    public function unregisterPublishEventHandler(?\Closure $callback = null): MqttClient
    {
        if ($callback === null) {
            $this->publishEventHandlers->removeAll($this->publishEventHandlers);
        } else {
            $this->publishEventHandlers->offsetUnset($callback);
        }

        /** @var MqttClient $this */
        return $this;
    }

    /**
     * Runs all the registered publish event handlers with the given parameters.
     * Each event handler is executed in a try-catch block to avoid spilling exceptions.
     */
    private function runPublishEventHandlers(string $topic, string $message, ?int $messageId, int $qualityOfService, bool $retain): void
    {
        foreach ($this->publishEventHandlers as $handler) {
            try {
                call_user_func($handler, $this, $topic, $message, $messageId, $qualityOfService, $retain);
            } catch (\Throwable $e) {
                $this->logger->error('Publish hook callback threw exception for published message on topic [{topic}].', [
                    'topic' => $topic,
                    'exception' => $e,
                ]);
            }
        }
    }

    /**
     * Registers an event handler which is called when a message is received from the broker.
     *
     * The message received event handler is passed the MQTT client as first, the topic as
     * second and the message as third parameter. As fourth parameter, the QoS level will be
     * passed and the retained flag as fifth.
     *
     * Example:
     * ```php
     * $mqtt->registerReceivedMessageEventHandler(function (
     *     MqttClient $mqtt,
     *     string $topic,
     *     string $message,
     *     int $qualityOfService,
     *     bool $retained
     * ) use ($logger) {
     *     $logger->info("Received message on topic [{$topic}]: {$message}");
     * });
     * ```
     *
     * Multiple event handlers can be registered at the same time.
     */
    public function registerMessageReceivedEventHandler(\Closure $callback): MqttClient
    {
        $this->messageReceivedEventHandlers->offsetSet($callback);

        /** @var MqttClient $this */
        return $this;
    }

    /**
     * Unregisters a message received event handler which prevents it from being called in the future.
     *
     * This does not affect other registered event handlers. It is possible
     * to unregister all registered event handlers by passing null as callback.
     */
    public function unregisterMessageReceivedEventHandler(?\Closure $callback = null): MqttClient
    {
        if ($callback === null) {
            $this->messageReceivedEventHandlers->removeAll($this->messageReceivedEventHandlers);
        } else {
            $this->messageReceivedEventHandlers->offsetUnset($callback);
        }

        /** @var MqttClient $this */
        return $this;
    }

    /**
     * Runs all the registered message received event handlers with the given parameters.
     * Each event handler is executed in a try-catch block to avoid spilling exceptions.
     */
    private function runMessageReceivedEventHandlers(string $topic, string $message, int $qualityOfService, bool $retained): void
    {
        foreach ($this->messageReceivedEventHandlers as $handler) {
            try {
                call_user_func($handler, $this, $topic, $message, $qualityOfService, $retained);
            } catch (\Throwable $e) {
                $this->logger->error('Received message hook callback threw exception for received message on topic [{topic}].', [
                    'topic' => $topic,
                    'exception' => $e,
                ]);
            }
        }
    }

    /**
     * Registers an event handler which is called when the client established a connection to the broker.
     * This also includes manual reconnects as well as auto-reconnects by the client itself.
     *
     * The event handler is passed the MQTT client as first argument,
     * followed by a flag which indicates whether an auto-reconnect occurred as second argument.
     *
     * Example:
     * ```php
     * $mqtt->registerConnectedEventHandler(function (
     *     MqttClient $mqtt,
     *     bool $isAutoReconnect
     * ) use ($logger) {
     *     if ($isAutoReconnect) {
     *         $logger->info("Client successfully auto-reconnected to the broker.);
     *     } else {
     *         $logger->info("Client successfully connected to the broker.");
     *     }
     * });
     * ```
     *
     * Multiple event handlers can be registered at the same time.
     */
    public function registerConnectedEventHandler(\Closure $callback): MqttClient
    {
        $this->connectedEventHandlers->offsetSet($callback);

        /** @var MqttClient $this */
        return $this;
    }

    /**
     * Unregisters a connected event handler which prevents it from being called in the future.
     *
     * This does not affect other registered event handlers. It is possible
     * to unregister all registered event handlers by passing null as callback.
     */
    public function unregisterConnectedEventHandler(?\Closure $callback = null): MqttClient
    {
        if ($callback === null) {
            $this->connectedEventHandlers->removeAll($this->connectedEventHandlers);
        } else {
            $this->connectedEventHandlers->offsetUnset($callback);
        }

        /** @var MqttClient $this */
        return $this;
    }

    /**
     * Runs all the registered connected event handlers.
     * Each event handler is executed in a try-catch block to avoid spilling exceptions.
     */
    private function runConnectedEventHandlers(bool $isAutoReconnect): void
    {
        foreach ($this->connectedEventHandlers as $handler) {
            try {
                call_user_func($handler, $this, $isAutoReconnect);
            } catch (\Throwable $e) {
                $this->logger->error('Connected hook callback threw exception.', ['exception' => $e]);
            }
        }
    }
}
