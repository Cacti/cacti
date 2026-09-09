<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Feature;

use PhpMqtt\Client\MqttClient;
use Tests\TestCase;

/**
 * Tests that the publish event handler work as intended.
 *
 * @package Tests\Feature
 */
class PublishEventHandlerTest extends TestCase
{
    public function test_publish_event_handlers_are_called_for_each_published_message(): void
    {
        $client = new MqttClient($this->mqttBrokerHost, $this->mqttBrokerPort, 'test-publish-event-handler');

        $handlerCallCount = 0;
        $handler = function () use (&$handlerCallCount) {
            $handlerCallCount++;
        };

        $client->registerPublishEventHandler($handler);

        $client->connect(null, true);
        $client->publish('foo/bar', 'baz-01');
        $client->publish('foo/bar', 'baz-02');
        $client->publish('foo/bar', 'baz-03');

        $this->assertSame(3, $handlerCallCount);

        $client->disconnect();
    }

    public function test_publish_event_handlers_can_be_unregistered_and_will_not_be_called_anymore(): void
    {
        $client = new MqttClient($this->mqttBrokerHost, $this->mqttBrokerPort, 'test-publish-event-handler');

        $handlerCallCount = 0;
        $handler = function () use (&$handlerCallCount) {
            $handlerCallCount++;
        };

        $client->registerPublishEventHandler($handler);

        $client->connect(null, true);
        $client->publish('foo/bar', 'baz-01');

        $this->assertSame(1, $handlerCallCount);

        $client->unregisterPublishEventHandler($handler);
        $client->publish('foo/bar', 'baz-02');

        $this->assertSame(1, $handlerCallCount);

        $client->registerPublishEventHandler($handler);
        $client->publish('foo/bar', 'baz-03');

        $this->assertSame(2, $handlerCallCount);

        $client->unregisterPublishEventHandler();
        $client->publish('foo/bar', 'baz-04');

        $this->assertSame(2, $handlerCallCount);

        $client->disconnect();
    }

    public function test_publish_event_handlers_can_throw_exceptions_which_does_not_affect_other_handlers_or_the_application(): void
    {
        $client = new MqttClient($this->mqttBrokerHost, $this->mqttBrokerPort, 'test-publish-event-handler');

        $handlerCallCount = 0;
        $handler1 = function () use (&$handlerCallCount) {
            $handlerCallCount++;
        };
        $handler2 = function () {
            throw new \Exception('Something went wrong!');
        };

        $client->registerPublishEventHandler($handler1);
        $client->registerPublishEventHandler($handler2);

        $client->connect(null, true);
        $client->publish('foo/bar', 'baz-01');

        $this->assertSame(1, $handlerCallCount);

        $client->disconnect();
    }
}
