#!/bin/sh
for file in `ls -1 po/*.po`;do
  ofile=$(basename --suffix=.po ${file})
  echo "$file to $ofile"
  msgfmt ${file} -o LC_MESSAGES/${ofile}.mo
done
