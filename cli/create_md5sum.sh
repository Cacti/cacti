#!/bin/bash
# A simple bash script to create MD5SUM from all cacti files

# IMPORTANT: Run script in root folder !
# /cli/create_md5sum.sh
echo "IMPORTANT: Run script in root folder !"

#Delete old .md5sum file
rm .md5sum

#Create new .md5sum
find . -type f -not -path "./.git/*" -print0 | xargs -0 md5sum > /tmp/MD5SUM; mv /tmp/MD5SUM .md5sum