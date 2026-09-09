<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Feature;

use PhpMqtt\Client\MqttClient;
use Tests\TestCase;

/**
 * Tests that the client utils (optional methods) work as intended.
 *
 * @package Tests\Feature
 */
class ClientUtilsTest extends TestCase
{
    public function test_counts_sent_and_received_bytes_correctly(): void
    {
        $client = new MqttClient($this->mqttBrokerHost, $this->mqttBrokerPort, 'test-byte-count');

        $client->connect(null, true);

        // Even the connection request and acknowledgement have bytes.
        $this->assertGreaterThan(0, $client->getSentBytes());
        $this->assertGreaterThan(0, $client->getReceivedBytes());

        // We therefore remember the current transfer stats and send some more data.
        $sentBytesBeforePublish     = $client->getSentBytes();
        $receivedBytesBeforePublish = $client->getReceivedBytes();

        $client->publish('foo/bar', 'baz-01', MqttClient::QOS_AT_MOST_ONCE);
        $client->publish('foo/bar', 'baz-02', MqttClient::QOS_AT_LEAST_ONCE);
        $client->publish('foo/bar', 'baz-03', MqttClient::QOS_EXACTLY_ONCE);

        $this->assertGreaterThan($sentBytesBeforePublish, $client->getSentBytes());
        $this->assertSame($receivedBytesBeforePublish, $client->getReceivedBytes());

        // Also we receive all acknowledgements to update our transfer stats correctly.
        $client->loop(true, true);

        $this->assertGreaterThan($receivedBytesBeforePublish, $client->getReceivedBytes());

        $client->disconnect();
    }

    public function test_is_connected_returns_correct_state(): void
    {
        $client = new MqttClient($this->mqttBrokerHost, $this->mqttBrokerPort, 'test-is-connected');

        $client->connect(null, true);

        $this->assertTrue($client->isConnected());

        $client->disconnect();

        $this->assertFalse($client->isConnected());

        $client->connect(null, true);

        $this->assertTrue($client->isConnected());

        $client->disconnect();

        $this->assertFalse($client->isConnected());
    }

    public function test_configured_client_id_is_returned_if_client_id_is_passed_to_constructor(): void
    {
        $clientId = 'test-configured-client-id';

        $client = new MqttClient($this->mqttBrokerHost, $this->mqttBrokerPort, $clientId);

        $this->assertSame($clientId, $client->getClientId());
    }

    public function test_generated_client_id_is_returned_if_no_client_id_is_passed_to_constructor(): void
    {
        $client = new MqttClient($this->mqttBrokerHost, $this->mqttBrokerPort);

        $this->assertNotNull($client->getClientId());
        $this->assertNotEmpty($client->getClientId());
    }

    public function test_configured_broker_host_and_port_are_returned(): void
    {
        $client = new MqttClient($this->mqttBrokerHost, $this->mqttBrokerPort);

        $this->assertSame($this->mqttBrokerHost, $client->getHost());
        $this->assertSame($this->mqttBrokerPort, $client->getPort());
    }
}
