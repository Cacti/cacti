<?php

/** @noinspection PhpDocSignatureInspection */
/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Feature;

use PhpMqtt\Client\ConnectionSettings;
use PhpMqtt\Client\Exceptions\ConfigurationInvalidException;
use PhpMqtt\Client\Exceptions\ConnectingToBrokerFailedException;
use PhpMqtt\Client\Exceptions\ProtocolNotSupportedException;
use PhpMqtt\Client\MqttClient;
use Tests\TestCase;

/**
 * Tests that the client cannot connect with invalid configuration.
 *
 * @package Tests\Feature
 */
class ConnectWithInvalidConfigurationTest extends TestCase
{
    public function invalidTimeouts(): array
    {
        return [
            [0],
            [-1],
            [-100],
        ];
    }

    /**
     * @dataProvider invalidTimeouts
     */
    public function test_connect_timeout_cannot_be_below_1_second(int $timeout): void
    {
        $connectionSettings = (new ConnectionSettings)->setConnectTimeout($timeout);

        $this->connectAndExpectConfigurationExceptionUsingSettings($connectionSettings);
    }

    /**
     * @dataProvider invalidTimeouts
     */
    public function test_socket_timeout_cannot_be_below_1_second(int $timeout): void
    {
        $connectionSettings = (new ConnectionSettings)->setSocketTimeout($timeout);

        $this->connectAndExpectConfigurationExceptionUsingSettings($connectionSettings);
    }

    /**
     * @dataProvider invalidTimeouts
     */
    public function test_resend_timeout_cannot_be_below_1_second(int $timeout): void
    {
        $connectionSettings = (new ConnectionSettings)->setResendTimeout($timeout);

        $this->connectAndExpectConfigurationExceptionUsingSettings($connectionSettings);
    }

    public function invalidKeepAliveIntervals(): array
    {
        return [
            [0],
            [-1],
            [-100],
            [65536],
            [100000],
        ];
    }

    /**
     * @dataProvider invalidKeepAliveIntervals
     */
    public function test_keep_alive_interval_cannot_be_value_below_1_or_greater_than_65535(int $keepAliveInterval): void
    {
        $connectionSettings = (new ConnectionSettings)->setKeepAliveInterval($keepAliveInterval);

        $this->connectAndExpectConfigurationExceptionUsingSettings($connectionSettings);
    }

    public function invalidUsernames(): array
    {
        return [
            [''],
            [' '],
            ['  '],
            ['	'],
        ];
    }

    /**
     * @dataProvider invalidUsernames
     */
    public function test_username_cannot_be_empty_or_whitespace(string $username): void
    {
        $connectionSettings = (new ConnectionSettings)->setUsername($username);

        $this->connectAndExpectConfigurationExceptionUsingSettings($connectionSettings);
    }

    public function invalidLastWillTopics(): array
    {
        return [
            [''],
            [' '],
            ['  '],
            ['	'],
        ];
    }

    /**
     * @dataProvider invalidLastWillTopics
     */
    public function test_last_will_topic_cannot_be_empty_or_whitespace(string $topic): void
    {
        $connectionSettings = (new ConnectionSettings)->setLastWillTopic($topic);

        $this->connectAndExpectConfigurationExceptionUsingSettings($connectionSettings);
    }

    public function invalidLastWillQualityOfService(): array
    {
        return [
            [-1],
            [3],
        ];
    }

    /**
     * @dataProvider invalidLastWillQualityOfService
     */
    public function test_last_will_quality_of_service_cannot_be_outside_the_0_to_2_range(int $qualityOfService): void
    {
        $connectionSettings = (new ConnectionSettings)->setLastWillQualityOfService($qualityOfService);

        $this->connectAndExpectConfigurationExceptionUsingSettings($connectionSettings);
    }

    public function test_tls_certificate_authority_file_cannot_be_invalid_file_path(): void
    {
        $connectionSettings = (new ConnectionSettings)->setTlsCertificateAuthorityFile(__DIR__.'/not_existing_file');

        $this->connectAndExpectConfigurationExceptionUsingSettings($connectionSettings);
    }

    public function test_tls_certificate_authority_path_cannot_be_invalid_directory_path(): void
    {
        $connectionSettings = (new ConnectionSettings)->setTlsCertificateAuthorityPath(__DIR__.'/not_existing_directory');

        $this->connectAndExpectConfigurationExceptionUsingSettings($connectionSettings);
    }

    public function test_tls_client_certificate_file_cannot_be_invalid_file_path(): void
    {
        $connectionSettings = (new ConnectionSettings)->setTlsClientCertificateFile(__DIR__.'/not_existing_file');

        $this->connectAndExpectConfigurationExceptionUsingSettings($connectionSettings);
    }

    public function test_tls_client_certificate_key_file_cannot_be_invalid_file_path(): void
    {
        $connectionSettings = (new ConnectionSettings)->setTlsClientCertificateKeyFile(__DIR__.'/not_existing_file');

        $this->connectAndExpectConfigurationExceptionUsingSettings($connectionSettings);
    }

    public function test_tls_client_certificate_file_must_be_set_if_client_certificate_key_file_is_set(): void
    {
        $connectionSettings = (new ConnectionSettings)->setTlsClientCertificateKeyFile(__DIR__.'/../resources/invalid-test-certificate.key');

        $this->connectAndExpectConfigurationExceptionUsingSettings($connectionSettings);
    }

    public function test_tls_client_certificate_key_file_must_be_set_if_client_certificate_key_passphrase_is_set(): void
    {
        $connectionSettings = (new ConnectionSettings)
            ->setTlsClientCertificateFile(__DIR__.'/../resources/invalid-test-certificate.crt')
            ->setTlsClientCertificateKeyPassphrase('some');

        $this->connectAndExpectConfigurationExceptionUsingSettings($connectionSettings);
    }

    /**
     * Performs the actual connection test using the given connection settings. Expects the settings to be invalid.
     *
     * @throws ConfigurationInvalidException
     * @throws ConnectingToBrokerFailedException
     * @throws ProtocolNotSupportedException
     */
    private function connectAndExpectConfigurationExceptionUsingSettings(ConnectionSettings $connectionSettings): void
    {
        $client = new MqttClient($this->mqttBrokerHost, $this->mqttBrokerPort, 'test-invalid-connection-settings');

        $this->expectException(ConfigurationInvalidException::class);
        $client->connect($connectionSettings);
    }
}
