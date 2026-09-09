<?xml version="1.0"?>
<hivemq xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="https://raw.githubusercontent.com/peez80/docker-hivemq/master/hivemq-config.xsd"
>
    <listeners>
        <!-- MQTT port without TLS -->
        <tcp-listener>
            <port>1883</port>
            <bind-address>0.0.0.0</bind-address>
        </tcp-listener>

        <!-- MQTT port with TLS but without client certificate validation -->
        <tls-tcp-listener>
            <port>8883</port>
            <bind-address>0.0.0.0</bind-address>
            <tls>
                <keystore>
                    <path>/hivemq-certs/server.jks</path>
                    <password>s3cr3t</password>
                    <private-key-password>s3cr3t</private-key-password>
                </keystore>
                <protocols>
                    <protocol>TLSv1.3</protocol>
                    <protocol>TLSv1.2</protocol>
                    <protocol>TLSv1.1</protocol>
                    <protocol>TLSv1</protocol>
                </protocols>
            </tls>
        </tls-tcp-listener>

        <!-- MQTT port with TLS and with client certificate validation -->
        <tls-tcp-listener>
            <port>8884</port>
            <bind-address>0.0.0.0</bind-address>
            <tls>
                <client-authentication-mode>REQUIRED</client-authentication-mode>
                <truststore>
                    <path>/hivemq-certs/ca.jks</path>
                    <password>s3cr3t</password>
                </truststore>
                <keystore>
                    <path>/hivemq-certs/server.jks</path>
                    <password>s3cr3t</password>
                    <private-key-password>s3cr3t</private-key-password>
                </keystore>
                <protocols>
                    <protocol>TLSv1.3</protocol>
                    <protocol>TLSv1.2</protocol>
                    <protocol>TLSv1.1</protocol>
                    <protocol>TLSv1</protocol>
                </protocols>
            </tls>
        </tls-tcp-listener>

    </listeners>
</hivemq>
