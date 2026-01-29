<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Feature;

use PhpMqtt\Client\MqttClient;
use Tests\TestCase;

/**
 * Tests that the connected event handler work as intended.
 *
 * @package Tests\Feature
 */
class ConnectedEventHandlerTest extends TestCase
{
    public function test_connected_event_handlers_are_called_every_time_the_client_connects_successfully(): void
    {
        $client = new MqttClient($this->mqttBrokerHost, $this->mqttBrokerPort, 'test-connected-event-handler');

        $handlerCallCount = 0;
        $handler = function () use (&$handlerCallCount) {
            $handlerCallCount++;
        };

        $client->registerConnectedEventHandler($handler);
        $client->connect();

        $this->assertSame(1, $handlerCallCount);

        $client->disconnect();
        $client->connect();

        $this->assertSame(2, $handlerCallCount);

        $client->disconnect();
        $client->connect();

        $this->assertSame(3, $handlerCallCount);

        $client->disconnect();
    }

    public function test_connected_event_handlers_can_be_unregistered_and_will_not_be_called_anymore(): void
    {
        $client = new MqttClient($this->mqttBrokerHost, $this->mqttBrokerPort, 'test-connected-event-handler');

        $handlerCallCount = 0;
        $handler = function () use (&$handlerCallCount) {
            $handlerCallCount++;
        };

        $client->registerConnectedEventHandler($handler);
        $client->connect();

        $this->assertSame(1, $handlerCallCount);

        $client->unregisterConnectedEventHandler($handler);
        $client->disconnect();
        $client->connect();

        $this->assertSame(1, $handlerCallCount);

        $client->registerConnectedEventHandler($handler);
        $client->disconnect();
        $client->connect();

        $this->assertSame(2, $handlerCallCount);

        $client->unregisterConnectedEventHandler($handler);
        $client->disconnect();
        $client->connect();

        $this->assertSame(2, $handlerCallCount);

        $client->disconnect();
    }

    public function test_connected_event_handlers_can_throw_exceptions_which_does_not_affect_other_handlers_or_the_application(): void
    {
        $client = new MqttClient($this->mqttBrokerHost, $this->mqttBrokerPort, 'test-connected-event-handler');

        $handlerCallCount = 0;
        $handler1 = function () use (&$handlerCallCount) {
            $handlerCallCount++;
        };
        $handler2 = function () {
            throw new \Exception('Something went wrong!');
        };

        $client->registerConnectedEventHandler($handler1);
        $client->registerConnectedEventHandler($handler2);

        $client->connect();

        $this->assertSame(1, $handlerCallCount);

        $client->disconnect();
    }

    public function test_connected_event_handler_is_passed_the_mqtt_client_and_the_auto_reconnect_flag_as_arguments(): void
    {
        $client = new MqttClient($this->mqttBrokerHost, $this->mqttBrokerPort, 'test-connected-event-handler');

        $client->registerConnectedEventHandler(function ($mqttClient, $isAutoReconnect) {
            $this->assertInstanceOf(MqttClient::class, $mqttClient);
            $this->assertIsBool($isAutoReconnect);
            $this->assertFalse($isAutoReconnect);
        });

        $client->connect();
        $client->disconnect();
    }
}
