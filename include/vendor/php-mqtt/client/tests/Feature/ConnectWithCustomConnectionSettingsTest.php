<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Feature;

use PhpMqtt\Client\ConnectionSettings;
use PhpMqtt\Client\MqttClient;
use Tests\TestCase;

/**
 * Tests that the client is able to connect to a broker with custom connection settings.
 *
 * @package Tests\Feature
 */
class ConnectWithCustomConnectionSettingsTest extends TestCase
{
    public function test_connecting_using_mqtt31_with_custom_connection_settings_works_as_intended(): void
    {
        $client = new MqttClient($this->mqttBrokerHost, $this->mqttBrokerPortWithAuthentication, 'test-custom-connection-settings', MqttClient::MQTT_3_1);

        $connectionSettings = (new ConnectionSettings)
            ->setLastWillTopic('foo/last/will')
            ->setLastWillMessage('baz is out!')
            ->setLastWillQualityOfService(MqttClient::QOS_AT_MOST_ONCE)
            ->setRetainLastWill(true)
            ->setConnectTimeout(3)
            ->setSocketTimeout(3)
            ->setResendTimeout(3)
            ->setKeepAliveInterval(30)
            ->setUsername($this->mqttBrokerUsername)
            ->setPassword($this->mqttBrokerPassword)
            ->setUseTls(false)
            ->setTlsCertificateAuthorityFile(null)
            ->setTlsCertificateAuthorityPath(null)
            ->setTlsClientCertificateFile(null)
            ->setTlsClientCertificateKeyFile(null)
            ->setTlsClientCertificateKeyPassphrase(null)
            ->setTlsVerifyPeer(false)
            ->setTlsVerifyPeerName(false)
            ->setTlsSelfSignedAllowed(true);

        $client->connect($connectionSettings);

        $this->assertTrue($client->isConnected());

        $client->disconnect();
    }

    public function test_connecting_using_mqtt311_with_custom_connection_settings_works_as_intended(): void
    {
        $client = new MqttClient($this->mqttBrokerHost, $this->mqttBrokerPortWithAuthentication, 'test-custom-connection-settings', MqttClient::MQTT_3_1_1);

        $connectionSettings = (new ConnectionSettings)
            ->setLastWillTopic('foo/last/will')
            ->setLastWillMessage('baz is out!')
            ->setLastWillQualityOfService(MqttClient::QOS_AT_MOST_ONCE)
            ->setRetainLastWill(true)
            ->setConnectTimeout(3)
            ->setSocketTimeout(3)
            ->setResendTimeout(3)
            ->setKeepAliveInterval(30)
            ->setUsername($this->mqttBrokerUsername)
            ->setPassword($this->mqttBrokerPassword)
            ->setUseTls(false)
            ->setTlsCertificateAuthorityFile(null)
            ->setTlsCertificateAuthorityPath(null)
            ->setTlsClientCertificateFile(null)
            ->setTlsClientCertificateKeyFile(null)
            ->setTlsClientCertificateKeyPassphrase(null)
            ->setTlsVerifyPeer(false)
            ->setTlsVerifyPeerName(false)
            ->setTlsSelfSignedAllowed(true);

        $client->connect($connectionSettings);

        $this->assertTrue($client->isConnected());

        $client->disconnect();
    }
}
