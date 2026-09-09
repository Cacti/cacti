<?php

declare(strict_types=1);

namespace Tests;

/**
 * A base class for all tests.
 *
 * @package Tests
 */
abstract class TestCase extends \PHPUnit\Framework\TestCase
{
    protected string $mqttBrokerHost;
    protected int $mqttBrokerPort;
    protected int $mqttBrokerPortWithAuthentication;
    protected int $mqttBrokerTlsPort;
    protected int $mqttBrokerTlsWithClientCertificatePort;

    protected ?string $mqttBrokerUsername = null;
    protected ?string $mqttBrokerPassword = null;

    protected bool $skipTlsTests;
    protected string $tlsCertificateDirectory;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->mqttBrokerHost                         = getenv('MQTT_BROKER_HOST');
        $this->mqttBrokerPort                         = intval(getenv('MQTT_BROKER_PORT'));
        $this->mqttBrokerPortWithAuthentication       = intval(getenv('MQTT_BROKER_PORT_WITH_AUTHENTICATION'));
        $this->mqttBrokerTlsPort                      = intval(getenv('MQTT_BROKER_TLS_PORT'));
        $this->mqttBrokerTlsWithClientCertificatePort = intval(getenv('MQTT_BROKER_TLS_WITH_CLIENT_CERT_PORT'));

        $this->mqttBrokerUsername                     = getenv('MQTT_BROKER_USERNAME') ?: null;
        $this->mqttBrokerPassword                     = getenv('MQTT_BROKER_PASSWORD') ?: null;

        $this->skipTlsTests            = getenv('SKIP_TLS_TESTS') === 'true';
        $this->tlsCertificateDirectory = rtrim(getenv('TLS_CERT_DIR'), '/');
    }
}
