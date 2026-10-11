#!/bin/sh
# Build-only plugin fetch; no Git metadata or executable Git remains at runtime.
set -eu

repository=$1
reference=$2
destination=$3

case "$reference" in
    ''|-*) printf '%s\n' 'Plugin reference must be a commit, branch, or tag.' >&2; exit 1 ;;
esac

mkdir -p "$destination"
git -C "$destination" init --quiet
git -C "$destination" remote add -- origin "$repository"
git -C "$destination" fetch --quiet --depth=1 --no-tags -- origin "$reference"
git -C "$destination" checkout --quiet --detach FETCH_HEAD
rm -rf "$destination/.git"
