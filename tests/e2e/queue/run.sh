#!/usr/bin/env bash
set -euo pipefail

project="cacti-queue-e2e-${GITHUB_RUN_ID:-local}-$$"
compose=(docker compose --project-name "$project" --file tests/e2e/queue/compose.yml)

cleanup() {
	"${compose[@]}" down --volumes --remove-orphans
}

trap cleanup EXIT

"${compose[@]}" up --detach --build
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
