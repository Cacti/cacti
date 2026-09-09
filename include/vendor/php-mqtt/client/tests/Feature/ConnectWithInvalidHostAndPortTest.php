<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Feature;

use PhpMqtt\Client\Exceptions\ConnectingToBrokerFailedException;
use PhpMqtt\Client\MqttClient;
use Tests\TestCase;

/**
 * Tests that the client throws an exception if connecting using invalid host and port.
 *
 * @package Tests\Feature
 */
class ConnectWithInvalidHostAndPortTest extends TestCase
{
    public function test_throws_exception_when_connecting_using_invalid_host_and_port(): void
    {
        $client = new MqttClient('127.0.0.1', 56565, 'test-invalid-host');

        $this->expectException(ConnectingToBrokerFailedException::class);
        $this->expectExceptionCode(ConnectingToBrokerFailedException::EXCEPTION_CONNECTION_SOCKET_ERROR);

        try {
            $client->connect(null, true);
        } catch (ConnectingToBrokerFailedException $e) {
            $this->assertGreaterThan(0, $e->getConnectionErrorCode());
            $this->assertNotEmpty($e->getConnectionErrorMessage());

            throw $e;
        }
    }
}
