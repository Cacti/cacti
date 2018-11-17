#!/bin/sh
for file in `ls -1 po/*.po`;do
  ofile=$(basename --suffix=.po ${file})
  echo "Converting $file to LC_MESSAGES/${ofile}.mo"
  msgfmt ${file} -o LC_MESSAGES/${ofile}.mo
done
