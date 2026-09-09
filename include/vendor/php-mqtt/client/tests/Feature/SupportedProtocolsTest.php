<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Feature;

use PhpMqtt\Client\Exceptions\ProtocolNotSupportedException;
use PhpMqtt\Client\MqttClient;
use Tests\TestCase;

/**
 * Tests the protocols supported (and not supported) by the client.
 *
 * @package Tests\Feature
 */
class SupportedProtocolsTest extends TestCase
{
    public function test_client_supports_mqtt_3_1_protocol(): void
    {
        $client = new MqttClient($this->mqttBrokerHost, $this->mqttBrokerPort, 'test-protocol', MqttClient::MQTT_3_1);

        $this->assertInstanceOf(MqttClient::class, $client);
    }

    public function test_client_supports_mqtt_3_1_1_protocol(): void
    {
        $client = new MqttClient($this->mqttBrokerHost, $this->mqttBrokerPort, 'test-protocol', MqttClient::MQTT_3_1_1);

        $this->assertInstanceOf(MqttClient::class, $client);
    }

    public function test_client_does_not_support_mqtt_3_protocol(): void
    {
        $this->expectException(ProtocolNotSupportedException::class);

        new MqttClient($this->mqttBrokerHost, $this->mqttBrokerPort, 'test-protocol', '3');
    }

    public function test_client_does_not_support_mqtt_5_protocol(): void
    {
        $this->expectException(ProtocolNotSupportedException::class);

        new MqttClient($this->mqttBrokerHost, $this->mqttBrokerPort, 'test-protocol', '5');
    }
}
