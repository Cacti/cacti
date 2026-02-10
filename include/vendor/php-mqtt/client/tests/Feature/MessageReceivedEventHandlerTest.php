<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Feature;

use PhpMqtt\Client\MqttClient;
use Tests\TestCase;

/**
 * Tests that the message received event handler work as intended.
 *
 * @package Tests\Feature
 */
class MessageReceivedEventHandlerTest extends TestCase
{
    public function test_message_received_event_handlers_are_called_for_each_received_message(): void
    {
        // We connect and subscribe to a topic using the first client.
        $subscriber = new MqttClient($this->mqttBrokerHost, $this->mqttBrokerPort, 'subscriber');
        $subscriber->connect(null, true);

        $handlerCallCount = 0;
        $handler = function (MqttClient $client, string $topic, string $message, int $qualityOfService, bool $retained) use (&$handlerCallCount) {
            $handlerCallCount++;

            $this->assertSame('foo/bar/baz', $topic);
            $this->assertSame('hello world', $message);
            $this->assertSame(0, $qualityOfService);
            $this->assertFalse($retained);

            $client->interrupt();
        };

        $subscriber->registerMessageReceivedEventHandler($handler);
        $subscriber->subscribe('foo/bar/baz');

        // We publish a message from a second client on the same topic.
        $publisher = new MqttClient($this->mqttBrokerHost, $this->mqttBrokerPort, 'publisher');
        $publisher->connect(null, true);

        $publisher->publish('foo/bar/baz', 'hello world', 0, false);

        // Then we loop on the subscriber to (hopefully) receive the published message.
        $subscriber->loop(true);

        $this->assertSame(1, $handlerCallCount);

        // Finally, we disconnect for a graceful shutdown on the broker side.
        $publisher->disconnect();
        $subscriber->disconnect();
    }

    public function test_message_received_event_handler_can_be_unregistered_and_will_not_be_called_anymore(): void
    {
        // We connect and subscribe to a topic using the first client.
        $subscriber = new MqttClient($this->mqttBrokerHost, $this->mqttBrokerPort, 'subscriber');
        $subscriber->connect(null, true);

        $callCount = 0;
        $handler = function (MqttClient $client, string $topic, string $message, int $qualityOfService, bool $retained) use (&$handler, &$callCount) {
            $callCount++;

            $this->assertSame('foo/bar/baz/01', $topic);
            $this->assertSame('hello world', $message);
            $this->assertSame(0, $qualityOfService);
            $this->assertFalse($retained);

            $client->unregisterMessageReceivedEventHandler($handler);
            $client->interrupt();
        };

        $subscriber->registerMessageReceivedEventHandler($handler);
        $subscriber->subscribe('foo/bar/baz/+');

        // We publish a message from a second client on the same topic.
        $publisher = new MqttClient($this->mqttBrokerHost, $this->mqttBrokerPort, 'publisher');
        $publisher->connect(null, true);

        $publisher->publish('foo/bar/baz/01', 'hello world', 0, false);
        $publisher->publish('foo/bar/baz/02', 'hello world', 0, false);

        // Then we loop on the subscriber to (hopefully) receive the published message.
        $subscriber->loop(true);

        $this->assertSame(1, $callCount);

        // Finally, we disconnect for a graceful shutdown on the broker side.
        $publisher->disconnect();
        $subscriber->disconnect();
    }

    public function test_message_received_event_handlers_can_be_unregistered_and_will_not_be_called_anymore(): void
    {
        // We connect and subscribe to a topic using the first client.
        $subscriber = new MqttClient($this->mqttBrokerHost, $this->mqttBrokerPort, 'subscriber');
        $subscriber->connect(null, true);

        $callCount = 0;
        $handler = function (MqttClient $client, string $topic, string $message, int $qualityOfService, bool $retained) use (&$callCount) {
            $callCount++;

            $this->assertSame('foo/bar/baz/01', $topic);
            $this->assertSame('hello world', $message);
            $this->assertSame(0, $qualityOfService);
            $this->assertFalse($retained);

            $client->unregisterMessageReceivedEventHandler();
            $client->interrupt();
        };

        $subscriber->registerMessageReceivedEventHandler($handler);
        $subscriber->subscribe('foo/bar/baz/+');

        // We publish a message from a second client on the same topic.
        $publisher = new MqttClient($this->mqttBrokerHost, $this->mqttBrokerPort, 'publisher');
        $publisher->connect(null, true);

        $publisher->publish('foo/bar/baz/01', 'hello world', 0, false);
        $publisher->publish('foo/bar/baz/02', 'hello world', 0, false);

        // Then we loop on the subscriber to (hopefully) receive the published message.
        $subscriber->loop(true);

        $this->assertSame(1, $callCount);

        // Finally, we disconnect for a graceful shutdown on the broker side.
        $publisher->disconnect();
        $subscriber->disconnect();
    }

    public function test_message_received_event_handlers_can_throw_exceptions_which_does_not_affect_other_handlers_or_the_application(): void
    {
        // We connect and subscribe to a topic using the first client.
        $subscriber = new MqttClient($this->mqttBrokerHost, $this->mqttBrokerPort, 'subscriber');
        $subscriber->connect(null, true);

        $handlerCallCount = 0;
        $handler1 = function () {
            throw new \Exception('Something went wrong!');
        };
        $handler2 = function (MqttClient $client) use (&$handlerCallCount) {
            $handlerCallCount++;

            $client->interrupt();
        };

        $subscriber->registerMessageReceivedEventHandler($handler1);
        $subscriber->registerMessageReceivedEventHandler($handler2);
        $subscriber->subscribe('foo/bar/baz');

        // We publish a message from a second client on the same topic.
        $publisher = new MqttClient($this->mqttBrokerHost, $this->mqttBrokerPort, 'publisher');
        $publisher->connect(null, true);

        $publisher->publish('foo/bar/baz', 'hello world', 0, false);

        // Then we loop on the subscriber to (hopefully) receive the published message.
        $subscriber->loop(true);

        $this->assertSame(1, $handlerCallCount);

        // Finally, we disconnect for a graceful shutdown on the broker side.
        $publisher->disconnect();
        $subscriber->disconnect();
    }
}
