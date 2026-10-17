#!/usr/bin/env bash
set -euo pipefail

project="cacti-queue-e2e-${GITHUB_RUN_ID:-local}-$$"
compose=(docker compose --project-name "$project" --file tests/e2e/queue/compose.yml)

cleanup() {
	"${compose[@]}" down --volumes --remove-orphans
}

trap cleanup EXIT

"${compose[@]}" up --detach --build
if [[ "${CACTI_TEST_COVERAGE:-0}" == 1 ]]; then
	: "${CACTI_PR_COVERAGE_OUTPUT:?Coverage output directory is required}"
	"${compose[@]}" exec --no-TTY app sh -c 'pecl install xdebug-3.5.3 && docker-php-ext-enable xdebug && mkdir -p /tmp/cacti-pr-coverage && printf "%s\n" "xdebug.mode=coverage" "auto_prepend_file=/var/www/html/cacti/tests/Helpers/RequestCoverage.php" > /usr/local/etc/php/conf.d/cacti-coverage.ini'
fi

"${compose[@]}" exec --no-TTY app php tests/e2e/queue/setup.php
"${compose[@]}" exec --no-TTY app php tests/e2e/queue/producer.php alpha
"${compose[@]}" exec --no-TTY app php tests/e2e/queue/producer.php beta

"${compose[@]}" exec --no-TTY app php cli/queue_worker.php --queue=queue-e2e --once &
worker_one=$!
"${compose[@]}" exec --no-TTY app php cli/queue_worker.php --queue=queue-e2e --once &
worker_two=$!
wait "$worker_one"
wait "$worker_two"

"${compose[@]}" exec --no-TTY app php tests/e2e/queue/assert.php
health="$("${compose[@]}" exec --no-TTY app php cli/queue_admin.php --queue=queue-e2e --health)"

if [[ "$health" != *'"completed":2'* ]]; then
	echo "Queue health did not report two completed messages: $health" >&2
	exit 1
fi

if "${compose[@]}" exec --no-TTY app php cli/queue_admin.php --limit=0 >/dev/null 2>&1; then
	echo 'queue_admin.php accepted an invalid limit.' >&2
	exit 1
fi

if "${compose[@]}" exec --no-TTY app php cli/queue_worker.php --sleep=0 --once >/dev/null 2>&1; then
	echo 'queue_worker.php accepted an invalid sleep interval.' >&2
	exit 1
fi

echo "Queue admin health passed: $health"

"${compose[@]}" exec --no-TTY app php tests/e2e/queue/contracts.php

if [[ "${CACTI_TEST_COVERAGE:-0}" == 1 ]]; then
	"${compose[@]}" exec --no-TTY db mysql --user=root --execute='CREATE DATABASE cacti_queue_test'
	db_address="$("${compose[@]}" port db 3306)"
	php_args=(-d memory_limit=2G)
	if [[ -n "${CACTI_QUEUE_XDEBUG_EXTENSION:-}" ]]; then
		php_args+=(-d "zend_extension=$CACTI_QUEUE_XDEBUG_EXTENSION")
	fi
	# The raw request coverage directory exists only inside the app container.
	CACTI_PR_RAW_COVERAGE='' CACTI_QUEUE_TEST_DSN="mysql:host=127.0.0.1;port=${db_address##*:};dbname=cacti_queue_test" \
		XDEBUG_MODE=coverage php "${php_args[@]}" include/vendor/bin/pest \
		--configuration=phpunit-changed.xml --testsuite=Unit,Integration --coverage-clover=coverage-changed.xml
	mkdir -p "$CACTI_PR_COVERAGE_OUTPUT"
	"${compose[@]}" cp app:/tmp/cacti-pr-coverage/. "$CACTI_PR_COVERAGE_OUTPUT"
fi
