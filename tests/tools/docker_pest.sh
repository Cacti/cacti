#!/usr/bin/env bash
set -euo pipefail

script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
repo_root="$(cd "${script_dir}/../.." && pwd)"
base_image="${CACTI_DOCKER_BASE_IMAGE:-cacti-web}"
test_image="${CACTI_DOCKER_TEST_IMAGE:-cacti-test}"

docker build \
	--tag "${base_image}" \
	--file "${repo_root}/docker/Dockerfile" \
	"${repo_root}/docker"

docker build \
	--tag "${test_image}" \
	--file "${repo_root}/docker/Dockerfile.test" \
	"${repo_root}"

docker run --rm \
	--env XDEBUG_MODE="${XDEBUG_MODE:-off}" \
	"${test_image}" \
	include/vendor/bin/pest --display-warnings "$@"
