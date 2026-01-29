<?php

/** @noinspection PhpDocSignatureInspection */
/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Feature;

use PhpMqtt\Client\ConnectionSettings;
use PhpMqtt\Client\MqttClient;
use Tests\TestCase;

/**
 * Tests that publishing messages and subscribing to topics using an MQTT broker works.
 *
 * @package Tests\Feature
 */
class PublishSubscribeTest extends TestCase
{
    public function publishSubscribeData(): array
    {
        $data = [
            [false, 'foo/bar/baz', 'foo/bar/baz', 'hello world', []],
            [false, 'foo/bar/+', 'foo/bar/baz', 'hello world', ['baz']],
            [false, 'foo/+/baz', 'foo/bar/baz', 'hello world', ['bar']],
            [false, 'foo/#', 'foo/bar/baz', 'hello world', ['bar/baz']],
            [false, 'foo/+/bar/#', 'foo/my/bar/baz', 'hello world', ['my', 'baz']],
            [false, 'foo/+/bar/#', 'foo/my/bar/baz/blub', 'hello world', ['my', 'baz/blub']],
            [false, 'foo/bar/baz', 'foo/bar/baz', random_bytes(2 * 1024 * 1024), []], // 2MB message
            [true, 'foo/bar/baz', 'foo/bar/baz', 'hello world', []],
            [true, 'foo/bar/+', 'foo/bar/baz', 'hello world', ['baz']],
            [true, 'foo/+/baz', 'foo/bar/baz', 'hello world', ['bar']],
            [true, 'foo/#', 'foo/bar/baz', 'hello world', ['bar/baz']],
            [true, 'foo/+/bar/#', 'foo/my/bar/baz', 'hello world', ['my', 'baz']],
            [true, 'foo/+/bar/#', 'foo/my/bar/baz/blub', 'hello world', ['my', 'baz/blub']],
            [true, 'foo/bar/baz', 'foo/bar/baz', random_bytes(2 * 1024 * 1024), []], // 2MB message
        ];

        // Because our tests are run against a real MQTT broker and some messages are retained,
        // we need to prevent false-positives by giving each test case its own 'test space' using a random prefix.
        for ($i = 0; $i < count($data); $i++) {
            $prefix = 'test/' . uniqid('', true) . '/';
            $data[$i][1] = $prefix . $data[$i][1];
            $data[$i][2] = $prefix . $data[$i][2];
        }

        return $data;
    }

    /**
     * @dataProvider publishSubscribeData
     */
    public function test_publishing_and_subscribing_using_quality_of_service_0_works_as_intended(
        bool $useBlockingSocket,
        string $subscriptionTopicFilter,
        string $publishTopic,
        string $publishMessage,
        array $matchedTopicWildcards
    ): void
    {
        // We connect and subscribe to a topic using the first client.
        $connectionSettings = (new ConnectionSettings())
            ->useBlockingSocket($useBlockingSocket);

        $subscriber = new MqttClient($this->mqttBrokerHost, $this->mqttBrokerPort, 'subscriber');
        $subscriber->connect($connectionSettings, true);

        $subscriber->subscribe(
            $subscriptionTopicFilter,
            function (string $topic, string $message, bool $retained, array $wildcards) use ($subscriber, $publishTopic, $publishMessage, $matchedTopicWildcards) {
                // By asserting something here, we will avoid a no-assertions-in-test warning, making the test pass.
                $this->assertEquals($publishTopic, $topic);
                $this->assertEquals($publishMessage, $message);
                $this->assertFalse($retained);
                $this->assertEquals($matchedTopicWildcards, $wildcards);

                $subscriber->interrupt(); // This allows us to exit the test as soon as possible.
            },
            0
        );

        // We publish a message from a second client on the same topic.
        $publisher = new MqttClient($this->mqttBrokerHost, $this->mqttBrokerPort, 'publisher');
        $publisher->connect(null, true);

        $publisher->publish($publishTopic, $publishMessage, 0, false);

        // Then we loop on the subscriber to (hopefully) receive the published message.
        $subscriber->loop(true);

        // Finally, we disconnect for a graceful shutdown on the broker side.
        $publisher->disconnect();
        $subscriber->disconnect();
    }

    /**
     * @dataProvider publishSubscribeData
     */
    public function test_publishing_and_subscribing_using_quality_of_service_0_with_message_retention_works_as_intended(
        bool $useBlockingSocket,
        string $subscriptionTopicFilter,
        string $publishTopic,
        string $publishMessage,
        array $matchedTopicWildcards
    ): void
    {
        // We publish a message from the first client, which disconnects before the other client even subscribes.
        $publisher = new MqttClient($this->mqttBrokerHost, $this->mqttBrokerPort, 'publisher');
        $publisher->connect(null, true);

        $publisher->publish($publishTopic, $publishMessage, 0, true);

        $publisher->disconnect();

        // Because we need to make sure the message reached the broker, we delay the execution for a short period (100ms) intentionally.
        // With higher QoS, this is replaced by awaiting delivery of the message.
        usleep(100_000);

        // We connect and subscribe to a topic using the second client.
        $connectionSettings = (new ConnectionSettings())
            ->useBlockingSocket($useBlockingSocket);

        $subscriber = new MqttClient($this->mqttBrokerHost, $this->mqttBrokerPort, 'subscriber');
        $subscriber->connect($connectionSettings, true);

        $subscriber->subscribe(
            $subscriptionTopicFilter,
            function (string $topic, string $message, bool $retained, array $wildcards) use ($subscriber, $publishTopic, $publishMessage, $matchedTopicWildcards) {
                // By asserting something here, we will avoid a no-assertions-in-test warning, making the test pass.
                $this->assertEquals($publishTopic, $topic);
                $this->assertEquals($publishMessage, $message);
                $this->assertTrue($retained);
                $this->assertEquals($matchedTopicWildcards, $wildcards);

                $subscriber->interrupt(); // This allows us to exit the test as soon as possible.
            },
            0
        );

        // Then we loop on the subscriber to (hopefully) receive the published message.
        $subscriber->loop(true);

        // Finally, we disconnect for a graceful shutdown on the broker side.
        $subscriber->disconnect();
    }

    /**
     * @dataProvider publishSubscribeData
     */
    public function test_publishing_and_subscribing_using_quality_of_service_1_works_as_intended(
        bool $useBlockingSocket,
        string $subscriptionTopicFilter,
        string $publishTopic,
        string $publishMessage,
        array $matchedTopicWildcards
    ): void
    {
        // We connect and subscribe to a topic using the first client.
        $connectionSettings = (new ConnectionSettings())
            ->useBlockingSocket($useBlockingSocket);

        $subscriber = new MqttClient($this->mqttBrokerHost, $this->mqttBrokerPort, 'subscriber');
        $subscriber->connect($connectionSettings, true);

        $subscriber->subscribe(
            $subscriptionTopicFilter,
            function (string $topic, string $message, bool $retained, array $wildcards) use ($subscriber, $publishTopic, $publishMessage, $matchedTopicWildcards) {
                // By asserting something here, we will avoid a no-assertions-in-test warning, making the test pass.
                $this->assertEquals($publishTopic, $topic);
                $this->assertEquals($publishMessage, $message);
                $this->assertFalse($retained);
                $this->assertEquals($matchedTopicWildcards, $wildcards);

                $subscriber->interrupt(); // This allows us to exit the test as soon as possible.
            },
            1
        );

        // We publish a message from a second client on the same topic.
        $publisher = new MqttClient($this->mqttBrokerHost, $this->mqttBrokerPort, 'publisher');
        $publisher->connect(null, true);

        $publisher->publish($publishTopic, $publishMessage, 1, false);

        // Then we loop on the subscriber to (hopefully) receive the published message.
        $subscriber->loop(true);

        // Finally, we disconnect for a graceful shutdown on the broker side.
        $publisher->disconnect();
        $subscriber->disconnect();
    }

    /**
     * @dataProvider publishSubscribeData
     */
    public function test_publishing_and_subscribing_using_quality_of_service_1_with_message_retention_works_as_intended(
        bool $useBlockingSocket,
        string $subscriptionTopicFilter,
        string $publishTopic,
        string $publishMessage,
        array $matchedTopicWildcards
    ): void
    {
        // We publish a message from the first client, which disconnects before the other client even subscribes.
        $publisher = new MqttClient($this->mqttBrokerHost, $this->mqttBrokerPort, 'publisher');
        $publisher->connect(null, true);

        $publisher->publish($publishTopic, $publishMessage, 1, true);
        $publisher->loop(true, true);

        $publisher->disconnect();

        // We connect and subscribe to a topic using the second client.
        $connectionSettings = (new ConnectionSettings())
            ->useBlockingSocket($useBlockingSocket);

        $subscriber = new MqttClient($this->mqttBrokerHost, $this->mqttBrokerPort, 'subscriber');
        $subscriber->connect($connectionSettings, true);

        $subscriber->subscribe(
            $subscriptionTopicFilter,
            function (string $topic, string $message, bool $retained, array $wildcards) use ($subscriber, $publishTopic, $publishMessage, $matchedTopicWildcards) {
                // By asserting something here, we will avoid a no-assertions-in-test warning, making the test pass.
                $this->assertEquals($publishTopic, $topic);
                $this->assertEquals($publishMessage, $message);
                $this->assertTrue($retained);
                $this->assertEquals($matchedTopicWildcards, $wildcards);

                $subscriber->interrupt(); // This allows us to exit the test as soon as possible.
            },
            1
        );

        // Then we loop on the subscriber to (hopefully) receive the published message.
        $subscriber->loop(true);

        // Finally, we disconnect for a graceful shutdown on the broker side.
        $subscriber->disconnect();
    }

    /**
     * @dataProvider publishSubscribeData
     */
    public function test_publishing_and_subscribing_using_quality_of_service_2_works_as_intended(
        bool $useBlockingSocket,
        string $subscriptionTopicFilter,
        string $publishTopic,
        string $publishMessage,
        array $matchedTopicWildcards
    ): void
    {
        // We connect and subscribe to a topic using the first client.
        $connectionSettings = (new ConnectionSettings())
            ->useBlockingSocket($useBlockingSocket);

        $subscriber = new MqttClient($this->mqttBrokerHost, $this->mqttBrokerPort, 'subscriber');
        $subscriber->connect($connectionSettings, true);

        $subscription = function (string $topic, string $message, bool $retained, array $wildcards) use ($subscriber, $subscriptionTopicFilter, $publishTopic, $publishMessage, $matchedTopicWildcards) {
            // By asserting something here, we will avoid a no-assertions-in-test warning, making the test pass.
            $this->assertEquals($publishTopic, $topic);
            $this->assertEquals($publishMessage, $message);
            $this->assertFalse($retained);
            $this->assertEquals($matchedTopicWildcards, $wildcards);

            $subscriber->unsubscribe($subscriptionTopicFilter);
            $subscriber->interrupt(); // This allows us to exit the test as soon as possible.
        };

        $subscriber->subscribe($subscriptionTopicFilter, $subscription, 2);

        // We publish a message from a second client on the same topic. The loop is called until all QoS 2 handshakes are done.
        $publisher = new MqttClient($this->mqttBrokerHost, $this->mqttBrokerPort, 'publisher');
        $publisher->connect(null, true);

        $publisher->publish($publishTopic, $publishMessage, 2, false);
        $publisher->loop(true, true);

        // Then we loop on the subscriber to (hopefully) receive the published message until the receive handshake is done.
        $subscriber->loop(true, true);

        // Finally, we disconnect for a graceful shutdown on the broker side.
        $publisher->disconnect();
        $subscriber->disconnect();
    }

    /**
     * @dataProvider publishSubscribeData
     */
    public function test_publishing_and_subscribing_using_quality_of_service_2_with_message_retention_works_as_intended(
        bool $useBlockingSocket,
        string $subscriptionTopicFilter,
        string $publishTopic,
        string $publishMessage,
        array $matchedTopicWildcards
    ): void
    {
        // We publish a message from the first client. The loop is called until all QoS 2 handshakes are done.
        $publisher = new MqttClient($this->mqttBrokerHost, $this->mqttBrokerPort, 'publisher');
        $publisher->connect(null, true);

        $publisher->publish($publishTopic, $publishMessage, 2, true);
        $publisher->loop(true, true);

        $publisher->disconnect();

        // We connect and subscribe to a topic using the second client.
        $connectionSettings = (new ConnectionSettings())
            ->useBlockingSocket($useBlockingSocket);

        $subscriber = new MqttClient($this->mqttBrokerHost, $this->mqttBrokerPort, 'subscriber');
        $subscriber->connect($connectionSettings, true);

        $subscription = function (string $topic, string $message, bool $retained, array $wildcards) use ($subscriber, $subscriptionTopicFilter, $publishTopic, $publishMessage, $matchedTopicWildcards) {
            // By asserting something here, we will avoid a no-assertions-in-test warning, making the test pass.
            $this->assertEquals($publishTopic, $topic);
            $this->assertEquals($publishMessage, $message);
            $this->assertTrue($retained);
            $this->assertEquals($matchedTopicWildcards, $wildcards);

            $subscriber->unsubscribe($subscriptionTopicFilter);
            $subscriber->interrupt(); // This allows us to exit the test as soon as possible.
        };

        $subscriber->subscribe($subscriptionTopicFilter, $subscription, 2);

        // Then we loop on the subscriber to (hopefully) receive the published message until the receive handshake is done.
        $subscriber->loop(true, true);

        // Finally, we disconnect for a graceful shutdown on the broker side.
        $subscriber->disconnect();
    }

    public function test_unsubscribe_stops_receiving_messages_on_topic(): void
    {
        // We connect and subscribe to a topic using the first client.
        $subscriber = new MqttClient($this->mqttBrokerHost, $this->mqttBrokerPort, 'subscriber');
        $subscriber->connect(null, true);

        $subscribedTopic      = 'test/foo/bar/baz';
        $receivedMessageCount = 0;
        $subscriber->subscribe(
            $subscribedTopic,
            function (string $topic, string $message, bool $retained) use ($subscriber, $subscribedTopic, &$receivedMessageCount) {
                $receivedMessageCount++;

                // By asserting something here, we will avoid a no-assertions-in-test warning, making the test pass.
                $this->assertEquals('test/foo/bar/baz', $topic);
                $this->assertEquals('hello world', $message);
                $this->assertFalse($retained);

                $subscriber->unsubscribe($subscribedTopic);
            },
            0
        );

        // We publish a message from a second client on the same topic.
        $publisher = new MqttClient($this->mqttBrokerHost, $this->mqttBrokerPort, 'publisher');
        $publisher->connect(null, true);

        $publisher->publish($subscribedTopic, 'hello world', 0, false);

        // Then we loop on the subscriber to (hopefully) receive the published message.
        $subscriber->loop(true, true);

        $this->assertSame(1, $receivedMessageCount);

        $publisher->publish($subscribedTopic, 'hello world #2', 0, false);
        $subscriber->loop(true, true);

        // Ensure no second message has been received since we are not subscribed anymore.
        $this->assertSame(1, $receivedMessageCount);

        // Finally, we disconnect for a graceful shutdown on the broker side.
        $publisher->disconnect();
        $subscriber->disconnect();
    }

    public function test_shared_subscriptions_using_quality_of_service_0_work_as_intended(): void
    {
        $subscriptionTopicFilter = '$share/test-shared-subscriptions/foo/+';
        $publishTopic = 'foo/bar';

        // We connect and subscribe to a topic using the first client with a shared subscription.
        $subscriber1 = new MqttClient($this->mqttBrokerHost, $this->mqttBrokerPort, 'subscriber1');
        $subscriber1->connect(null, true);

        $subscriber1->subscribe($subscriptionTopicFilter, function (string $topic, string $message, bool $retained) use ($subscriber1, $publishTopic) {
            // By asserting something here, we will avoid a no-assertions-in-test warning, making the test pass.
            $this->assertEquals($publishTopic, $topic);
            $this->assertEquals('hello world #1', $message);
            $this->assertFalse($retained);

            $subscriber1->interrupt(); // This allows us to exit the test as soon as possible.
        }, 0);

        // We connect and subscribe to a topic using the second client with a shared subscription.
        $subscriber2 = new MqttClient($this->mqttBrokerHost, $this->mqttBrokerPort, 'subscriber2');
        $subscriber2->connect(null, true);

        $subscriber2->subscribe($subscriptionTopicFilter, function (string $topic, string $message, bool $retained) use ($subscriber2, $publishTopic) {
            // By asserting something here, we will avoid a no-assertions-in-test warning, making the test pass.
            $this->assertEquals($publishTopic, $topic);
            $this->assertEquals('hello world #2', $message);
            $this->assertFalse($retained);

            $subscriber2->interrupt(); // This allows us to exit the test as soon as possible.
        }, 0);

        // We publish a message from a second client on the same topic.
        $publisher = new MqttClient($this->mqttBrokerHost, $this->mqttBrokerPort, 'publisher');
        $publisher->connect(null, true);

        $publisher->publish($publishTopic, 'hello world #1', 0, false);
        $publisher->publish($publishTopic, 'hello world #2', 0, false);

        // Then we loop on the subscribers to (hopefully) receive the published messages.
        $subscriber1->loop(true);
        $subscriber2->loop(true);

        // Finally, we disconnect for a graceful shutdown on the broker side.
        $publisher->disconnect();
        $subscriber1->disconnect();
        $subscriber2->disconnect();
    }
}
