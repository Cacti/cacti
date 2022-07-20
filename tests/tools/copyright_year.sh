#!/bin/bash

# ----------------------------------------------
# PHP Files First
# ----------------------------------------------
for file in `find . -name \*.php -print`;do 
  echo -n $file;
  grep '2004-2021' $file >/dev/null;
  result=$?

  if [[ $result -ne 1 ]]; then
    echo " Updating Copyright Data"
    sed -i s/"2004-2021 The Cacti Group"/"2004-2022 The Cacti Group"/g $file
  else
    echo " Skipping Copyright Data"
  fi
done

# ----------------------------------------------
# JavaScript Files First
# ----------------------------------------------
for file in `find . -name \*.js -print`;do 
  echo -n $file;
  grep '2004-2021 The Cacti Group' $file >/dev/null;
  result=$?

  if [[ $result -ne 1 ]]; then
    echo " Updating Copyright Data"
    sed -i s/"2004-2021 The Cacti Group"/"2004-2022 The Cacti Group"/g $file
  else
    echo " Skipping Copyright Data"
  fi
done
