#!/bin/sh
set -eu

found=0
failed=0

for file in install/templates/*.gz; do
  # An unmatched glob is left as the literal pattern.
  [ -e "$file" ] || continue
  found=$((found + 1))

  if ! php cli/import_package.php --filename="$file"; then
    echo "ERROR: Unable to import $file" >&2
    failed=$((failed + 1))
  fi
done

if [ "$found" -eq 0 ]; then
  echo "ERROR: No packages found in install/templates" >&2
  exit 1
fi

if [ "$failed" -gt 0 ]; then
  echo "ERROR: $failed of $found packages failed to import" >&2
  exit 1
fi
