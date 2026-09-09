#!/bin/sh

# Generate a new CA certificate and key.
openssl genrsa -out .ci/tls/ca.key 2048
openssl req -x509 -new -nodes -key .ci/tls/ca.key -days 1 -out .ci/tls/ca.crt -subj "/C=AT/ST=Vorarlberg/CN=php-mqtt Test CA"

# Copy ca.crt to a file named by the hashed subject of the certificate. This is required for PHP's capath option to find the certificate.
cp .ci/tls/ca.crt .ci/tls/$(openssl x509 -hash -noout -in .ci/tls/ca.crt).0

# Create a Java Trust Store from the CA certificate. This is used by HiveMQ.
keytool -import -file .ci/tls/ca.crt -alias ca -keystore .ci/tls/ca.jks -storepass s3cr3t -trustcacerts -noprompt

# Generate a new server certificate and key, signed by the created CA.
openssl genrsa -out .ci/tls/server.key 2048
openssl req -new -key .ci/tls/server.key -out .ci/tls/server.csr -sha512 -subj "/C=AT/ST=Vorarlberg/CN=localhost"
openssl x509 -req -in .ci/tls/server.csr -CA .ci/tls/ca.crt -CAkey .ci/tls/ca.key -CAcreateserial -out .ci/tls/server.crt -days 1 -sha512

# Generate a Java Key Store from the server certificate. This is used by HiveMQ.
openssl pkcs12 -export -in .ci/tls/server.crt -inkey .ci/tls/server.key -out .ci/tls/server.p12 -passout pass:s3cr3t
keytool -importkeystore -srckeystore .ci/tls/server.p12 -srcstoretype PKCS12 -destkeystore .ci/tls/server.jks -deststoretype JKS -srcstorepass s3cr3t -deststorepass s3cr3t -noprompt

# Generate a client certificate without passphrase, signed by the created CA.
openssl genrsa -out .ci/tls/client.key 2048
openssl req -new -key .ci/tls/client.key -out .ci/tls/client.csr -sha512 -subj "/C=AT/ST=Vorarlberg/CN=localhost"
openssl x509 -req -in .ci/tls/client.csr -CA .ci/tls/ca.crt -CAkey .ci/tls/ca.key -CAcreateserial -out .ci/tls/client.crt -days 1 -sha256

# Generate a client certificate with passphrase, signed by the created CA.
openssl genrsa -aes128 -passout pass:s3cr3t -out .ci/tls/client2.key 2048
openssl req -new -key .ci/tls/client2.key -passin pass:s3cr3t -out .ci/tls/client2.csr -sha512 -subj "/C=AT/ST=Vorarlberg/CN=localhost"
openssl x509 -req -in .ci/tls/client2.csr -CA .ci/tls/ca.crt -CAkey .ci/tls/ca.key -CAcreateserial -out .ci/tls/client2.crt -days 1 -sha256
