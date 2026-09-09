<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Feature;

use PhpMqtt\Client\Exceptions\ClientNotConnectedToBrokerException;
use PhpMqtt\Client\MqttClient;
use Tests\TestCase;

/**
 * Tests that the client throws an exception if not connected.
 *
 * @package Tests\Feature
 */
class ActionsWithoutActiveConnectionTest extends TestCase
{
    public function test_throws_exception_when_message_is_published_without_connecting_to_broker(): void
    {
        $client = new MqttClient($this->mqttBrokerHost, $this->mqttBrokerPort, 'test-not-connected');

        $this->expectException(ClientNotConnectedToBrokerException::class);
        $client->publish('foo/bar', 'baz');
    }

    public function test_throws_exception_when_topic_is_subscribed_without_connecting_to_broker(): void
    {
        $client = new MqttClient($this->mqttBrokerHost, $this->mqttBrokerPort, 'test-not-connected');

        $this->expectException(ClientNotConnectedToBrokerException::class);
        $client->subscribe('foo/bar', fn () => true);
    }

    public function test_throws_exception_when_topic_is_unsubscribed_without_connecting_to_broker(): void
    {
        $client = new MqttClient($this->mqttBrokerHost, $this->mqttBrokerPort, 'test-not-connected');

        $this->expectException(ClientNotConnectedToBrokerException::class);
        $client->unsubscribe('foo/bar');
    }

    public function test_throws_exception_when_disconnecting_without_connecting_to_broker_first(): void
    {
        $client = new MqttClient($this->mqttBrokerHost, $this->mqttBrokerPort, 'test-not-connected');

        $this->expectException(ClientNotConnectedToBrokerException::class);
        $client->disconnect();
    }
}
