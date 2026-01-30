<?php

declare(strict_types=1);

namespace Tests\Unit\MessageProcessors;

use PhpMqtt\Client\ConnectionSettings;
use PhpMqtt\Client\Logger;
use PhpMqtt\Client\Subscription;
use PhpMqtt\Client\MessageProcessors\Mqtt311MessageProcessor;
use PHPUnit\Framework\TestCase;

class Mqtt311MessageProcessorTest extends TestCase
{
    public const CLIENT_ID = 'test-client';

    /** @var Mqtt311MessageProcessor */
    protected $messageProcessor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->messageProcessor = new Mqtt311MessageProcessor('test-client', new Logger('test.local', 1883, self::CLIENT_ID));
    }

    public function tryFindMessageInBuffer_testDataProvider(): array
    {
        return [
            // No message and/or no knowledge about the remaining length of the message.
            [hex2bin(''), false, null, null],
            [hex2bin('20'), false, null, null],

            // Incomplete message with knowledge about the remaining length of the message.
            [hex2bin('2002'), false, null, 4],
            [hex2bin('200200'), false, null, 4],

            // Buffer contains only one complete message.
            [hex2bin('20020000'), true, hex2bin('20020000'), null],
            [hex2bin('800a0a03612f6201632f6402'), true, hex2bin('800a0a03612f6201632f6402'), null],

            // Buffer contains more than one complete message.
            [hex2bin('2002000044'), true, hex2bin('20020000'), null],
            [hex2bin('4002000044'), true, hex2bin('40020000'), null],
            [hex2bin('400200004412345678'), true, hex2bin('40020000'), null],
        ];
    }

    /**
     * @dataProvider tryFindMessageInBuffer_testDataProvider
     *
     * @param string|null $expectedMessage
     * @param int|null    $expectedRequiredBytes
     */
    public function test_tryFindMessageInBuffer_finds_messages_correctly(
        string $buffer,
        bool $expectedResult,
        ?string $expectedMessage,
        ?int $expectedRequiredBytes
    ): void
    {
        $message       = null;
        $requiredBytes = -1;

        $result = $this->messageProcessor->tryFindMessageInBuffer($buffer, strlen($buffer), $message, $requiredBytes);

        $this->assertEquals($expectedResult, $result);
        $this->assertEquals($expectedMessage, $message);
        if ($expectedRequiredBytes !== null) {
            $this->assertEquals($expectedRequiredBytes, $requiredBytes);
        } else {
            $this->assertEquals(-1, $requiredBytes);
        }
    }

    /**
     * Message format:
     *
     *   <fixed header><protocol name><protocol version><flags><keep alive><client id><will topic><will message><username><password>
     *
     * @return array[]
     * @throws \Exception
     */
    public function buildConnectMessage_testDataProvider(): array
    {
        return [
            // Default parameters
            [new ConnectionSettings(), false, hex2bin('101700044d5154540400000a000b') . self::CLIENT_ID],

            // Clean Session
            [new ConnectionSettings(), true, hex2bin('101700044d5154540402000a000b') . self::CLIENT_ID],

            // Username, Password and Clean Session
            [
                (new ConnectionSettings())
                    ->setUsername('foo')
                    ->setPassword('bar'),
                true,
                hex2bin('102100044d51545404c2000a000b') . self::CLIENT_ID . hex2bin('0003') . 'foo' . hex2bin('0003') . 'bar',
            ],

            // Last Will Topic, Last Will Message and Clean Session
            [
                (new ConnectionSettings())
                    ->setLastWillTopic('test/foo')
                    ->setLastWillMessage('bar')
                    ->setLastWillQualityOfService(1),
                true,
                hex2bin('102600044d515454040e000a000b') . self::CLIENT_ID . hex2bin('0008') . 'test/foo' . hex2bin('0003') . 'bar',
            ],

            // Last Will Topic, Last Will Message, Retain Last Will, Username, Password and Clean Session
            [
                (new ConnectionSettings())
                    ->setLastWillTopic('test/foo')
                    ->setLastWillMessage('bar')
                    ->setLastWillQualityOfService(2)
                    ->setRetainLastWill(true)
                    ->setUsername('blub')
                    ->setPassword('blubber'),
                true,
                hex2bin('103500044d51545404f6000a000b') . self::CLIENT_ID . hex2bin('0008') . 'test/foo' . hex2bin('0003') . 'bar'
                    . hex2bin('0004') . 'blub' . hex2bin('0007') . 'blubber',
            ],
        ];
    }

    /**
     * @dataProvider buildConnectMessage_testDataProvider
     */
    public function test_buildConnectMessage_builds_correct_message(
        ConnectionSettings $connectionSettings,
        bool $useCleanSession,
        string $expectedResult
    ): void
    {
        $result = $this->messageProcessor->buildConnectMessage($connectionSettings, $useCleanSession);

        $this->assertEquals($expectedResult, $result);
    }

    /**
     * Message format:
     *
     *   <fixed header><message id><topic><QoS>
     *
     * @return array[]
     * @throws \Exception
     */
    public function buildSubscribeMessage_testDataProvider(): array
    {
        $longTopic = random_bytes(130);

        return [
            // Simple QoS 0 subscription
            [42, [new Subscription('test/foo', 0)], hex2bin('82'.'0d00'.'2a00'.'08') . 'test/foo' . hex2bin('00')],

            // Wildcard QoS 2 subscription with high message id
            [43764, [new Subscription('test/foo/bar/baz/#', 2)], hex2bin('82'.'17aa'.'f400'.'12') . 'test/foo/bar/baz/#' . hex2bin('02')],

            // Long QoS 1 subscription with high message id
            [62304, [new Subscription($longTopic, 1)], hex2bin('82'.'8701'.'f360'.'0082') . $longTopic . hex2bin('01')],
        ];
    }

    /**
     * @dataProvider buildSubscribeMessage_testDataProvider
     *
     * @param Subscription[] $subscriptions
     */
    public function test_buildSubscribeMessage_builds_correct_message(
        int $messageId,
        array $subscriptions,
        string $expectedResult
    ): void
    {
        $result = $this->messageProcessor->buildSubscribeMessage($messageId, $subscriptions);

        $this->assertEquals($expectedResult, $result);
    }

    /**
     * Message format:
     *
     *   <fixed header><message id><topic>
     *
     * @return array[]
     * @throws \Exception
     */
    public function buildUnsubscribeMessage_testDataProvider(): array
    {
        $longTopic = random_bytes(130);

        return [
            // Simple unsubscribe without duplicate
            [42, ['test/foo'], false, hex2bin('a2'.'0c00'.'2a00'.'08') . 'test/foo'],

            // Wildcard unsubscribe with high message id as duplicate
            [43764, ['test/foo/bar/baz/#'], true, hex2bin('aa'.'16aa'.'f400'.'12') . 'test/foo/bar/baz/#'],

            // Long unsubscribe with high message id as duplicate
            [62304, [$longTopic], true, hex2bin('aa'.'8601'.'f360'.'0082') . $longTopic],
        ];
    }

    /**
     * @dataProvider buildUnsubscribeMessage_testDataProvider
     *
     * @param string[] $topics
     */
    public function test_buildUnsubscribeMessage_builds_correct_message(
        int $messageId,
        array $topics,
        bool $isDuplicate,
        string $expectedResult
    ): void
    {
        $result = $this->messageProcessor->buildUnsubscribeMessage($messageId, $topics, $isDuplicate);

        $this->assertEquals($expectedResult, $result);
    }

    /**
     * Message format:
     *
     *   <fixed header><topic><message id><payload>
     *
     * @return array[]
     * @throws \Exception
     */
    public function buildPublishMessage_testDataProvider(): array
    {
        $longMessage = random_bytes(424242);

        return [
            // Simple QoS 0 publish
            ['test/foo', 'hello world', 0, false, 42, false, hex2bin('30'.'17'.'0008') . 'test/foo' . hex2bin('002a') . 'hello world'],

            // Retained duplicate QoS 2 publish with long data and high message id
            ['test/foo', $longMessage, 2, true, 4242, true, hex2bin('3d'.'bef219'.'0008') . 'test/foo' . hex2bin('1092') . $longMessage],
        ];
    }

    /**
     * @dataProvider buildPublishMessage_testDataProvider
     */
    public function test_buildPublishMessage_builds_correct_message(
        string $topic,
        string $message,
        int $qualityOfService,
        bool $retain,
        int $messageId,
        bool $isDuplicate,
        string $expectedResult
    ): void
    {
        $result = $this->messageProcessor->buildPublishMessage(
            $topic,
            $message,
            $qualityOfService,
            $retain,
            $messageId,
            $isDuplicate
        );

        $this->assertEquals($expectedResult, $result);
    }

    /**
     * Message format:
     *
     *   <fixed header><message id>
     *
     * @return array[]
     * @throws \Exception
     */
    public function buildPublishAcknowledgementMessage_testDataProvider(): array
    {
        return [
            // Simple acknowledgement using small message id
            [42, hex2bin('40'.'02'.'002a')],

            // Simple acknowledgement using large message id
            [4242, hex2bin('40'.'02'.'1092')],
        ];
    }

    /**
     * @dataProvider buildPublishAcknowledgementMessage_testDataProvider
     */
    public function test_buildPublishAcknowledgementMessage_builds_correct_message(int $messageId, string $expectedResult): void
    {
        $result = $this->messageProcessor->buildPublishAcknowledgementMessage($messageId);

        $this->assertEquals($expectedResult, $result);
    }

    /**
     * Message format:
     *
     *   <fixed header><message id>
     *
     * @return array[]
     * @throws \Exception
     */
    public function buildPublishReceivedMessage_testDataProvider(): array
    {
        return [
            // Simple acknowledgement using small message id
            [42, hex2bin('50'.'02'.'002a')],

            // Simple acknowledgement using large message id
            [4242, hex2bin('50'.'02'.'1092')],
        ];
    }

    /**
     * @dataProvider buildPublishReceivedMessage_testDataProvider
     */
    public function test_buildPublishReceivedMessage_builds_correct_message(int $messageId, string $expectedResult): void
    {
        $result = $this->messageProcessor->buildPublishReceivedMessage($messageId);

        $this->assertEquals($expectedResult, $result);
    }

    /**
     * Message format:
     *
     *   <fixed header><message id>
     *
     * @return array[]
     * @throws \Exception
     */
    public function buildPublishCompleteMessage_testDataProvider(): array
    {
        return [
            // Simple acknowledgement using small message id
            [42, hex2bin('70'.'02'.'002a')],

            // Simple acknowledgement using large message id
            [4242, hex2bin('70'.'02'.'1092')],
        ];
    }

    /**
     * @dataProvider buildPublishCompleteMessage_testDataProvider
     */
    public function test_buildPublishCompleteMessage_builds_correct_message(int $messageId, string $expectedResult): void
    {
        $result = $this->messageProcessor->buildPublishCompleteMessage($messageId);

        $this->assertEquals($expectedResult, $result);
    }

    public function test_buildPingMessage_builds_correct_message(): void
    {
        $this->assertEquals(hex2bin('c000'), $this->messageProcessor->buildPingRequestMessage());
    }

    public function test_buildDisconnectMessage_builds_correct_message(): void
    {
        $this->assertEquals(hex2bin('e000'), $this->messageProcessor->buildDisconnectMessage());
    }
}
