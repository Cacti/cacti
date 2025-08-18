#!/bin/sh

for file in $(ls -1 install/templates/*.gz);do
  php cli/import_package.php --filename=$file
done
