<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Feature;

use PhpMqtt\Client\MqttClient;
use Tests\TestCase;

/**
 * Tests that the loop event handler work as intended.
 *
 * @package Tests\Feature
 */
class LoopEventHandlerTest extends TestCase
{
    public function test_loop_event_handlers_are_called_for_each_loop_iteration(): void
    {
        $client = new MqttClient($this->mqttBrokerHost, $this->mqttBrokerPort, 'test-loop-event-handler');

        $loopCount           = 0;
        $previousElapsedTime = 0;
        $client->registerLoopEventHandler(function (MqttClient $client, float $elapsedTime) use (&$loopCount, &$previousElapsedTime) {
            $this->assertGreaterThanOrEqual($previousElapsedTime, $elapsedTime);

            $previousElapsedTime = $elapsedTime;

            $loopCount++;

            if ($loopCount >= 3) {
                $client->interrupt();
                return;
            }
        });

        $client->connect(null, true);

        $client->loop();

        $this->assertSame(3, $loopCount);

        $client->disconnect();
    }

    public function test_loop_event_handler_can_be_unregistered_and_will_not_be_called_anymore(): void
    {
        $client = new MqttClient($this->mqttBrokerHost, $this->mqttBrokerPort, 'test-loop-event-handler');

        $loopCount = 0;
        $handler = function (MqttClient $client) use (&$loopCount) {
            $loopCount++;

            if ($loopCount >= 1) {
                $client->interrupt();
                return;
            }
        };

        $client->registerLoopEventHandler($handler);

        $client->connect(null, true);

        $client->loop();

        $this->assertSame(1, $loopCount);

        $client->unregisterLoopEventHandler($handler);

        $client->loop(true, true);

        $this->assertSame(1, $loopCount);

        $client->disconnect();
    }

    public function test_all_loop_event_handlers_can_be_unregistered_and_will_not_be_called_anymore(): void
    {
        $client = new MqttClient($this->mqttBrokerHost, $this->mqttBrokerPort, 'test-loop-event-handler');

        $loopCount1 = 0;
        $loopCount2 = 0;
        $handler1 = function (MqttClient $client) use (&$loopCount1) {
            $loopCount1++;

            if ($loopCount1 >= 1) {
                $client->interrupt();
                return;
            }
        };
        $handler2 = function () use (&$loopCount2) {
            $loopCount2++;
        };

        $client->registerLoopEventHandler($handler1);
        $client->registerLoopEventHandler($handler2);

        $client->connect(null, true);

        $client->loop();

        $this->assertSame(1, $loopCount1);
        $this->assertSame(1, $loopCount2);

        $client->unregisterLoopEventHandler();

        $client->loop(true, true);

        $this->assertSame(1, $loopCount1);
        $this->assertSame(1, $loopCount2);

        $client->disconnect();
    }

    public function test_loop_event_handlers_can_throw_exceptions_which_does_not_affect_other_handlers_or_the_application(): void
    {
        $client = new MqttClient($this->mqttBrokerHost, $this->mqttBrokerPort, 'test-publish-event-handler');

        $loopCount = 0;
        $handler1  = function () {
            throw new \Exception('Something went wrong!');
        };
        $handler2  = function (MqttClient $client) use (&$loopCount) {
            $loopCount++;

            if ($loopCount >= 1) {
                $client->interrupt();
                return;
            }
        };

        $client->registerLoopEventHandler($handler1);
        $client->registerLoopEventHandler($handler2);

        $client->connect(null, true);
        $client->loop(true);

        $this->assertSame(1, $loopCount);

        $client->disconnect();
    }
}
