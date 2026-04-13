#!/bin/sh

for file in install/templates/*.gz; do
  php cli/import_package.php --filename="$file"
done
