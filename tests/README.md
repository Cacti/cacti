# Tests

## Docker Security Slice

Run the same security-focused Docker test slice used by CI:

```bash
cat > .env.test <<'EOF'
WEB_PORT=18080
DB_ROOT_PASSWORD=testroot
DB_NAME=cacti
DB_USER=cacti
DB_PASSWORD=testpass
DB_PORT=13306
TIMEZONE=UTC
PHP_MEMORY_LIMIT=256M
DB_MAX_CONNECTIONS=50
DB_BUFFER_POOL_SIZE=256M
EOF

docker compose --env-file .env.test build
docker compose --env-file .env.test up -d
docker compose --env-file .env.test exec -T web include/vendor/bin/pest --colors=never \
  tests/Unit/Pr7036ReviewCoverageTest.php \
  tests/Unit/PathValidationIntegrationTest.php \
  tests/Unit/SecurityValidationHelpersTest.php \
  tests/Unit/RemoteAgentAuthTest.php
docker compose --env-file .env.test down -v --remove-orphans
rm -f .env.test
```
