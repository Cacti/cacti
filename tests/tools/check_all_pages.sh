#!/bin/bash
# ------------------------------------------------------------------------------
# This script is supposed to test cacti a little bit. At least each page and
# each link is tried. I mean to add checks for new CVE's (at least those that I
# can trigger with wget) as well.
# ------------------------------------------------------------------------------

mode=$1

# ------------------------------------------------------------------------------
# Get inputs from user (Interactive mode)
# ------------------------------------------------------------------------------
if [ "$mode" = "--interactive" ]; then
	echo "Enter Database username"
	read -r database_user
	echo "Enter Database Password"
	read -r database_pw
	echo "Enter Cacti Admin password"
	read -r login_pw
elif [ "$mode" = "--help" ]; then
	echo "Checks all Cacti pages using wget options"
	echo "Original script by team Debian."
	echo ""
	echo "usage: check_all_pages.sh [--interactive]"
	echo ""
else
	echo "Script is running in non-interactive mode ensure you fill out the DB credentials!!!"
	sleep 2 #Give user a chance to see the prompt

	database_user="cactiuser"
	database_pw="cactiuser"
	login_pw="admin"
fi

# ------------------------------------------------------------------------------
# Debugging
# ------------------------------------------------------------------------------
#set -xv

exec 2>&1

started=0

# ------------------------------------------------------------------------------
# OS Specific Paths
# ------------------------------------------------------------------------------
SCRIPT_PATH=$( cd -- "$( dirname -- "${BASH_SOURCE[0]}" )" &> /dev/null && pwd )
BASE_PATH=$( cd -- "${SCRIPT_PATH}/../../" &> /dev/null && pwd )

echo "Using base path of ${BASE_PATH}"

CACTI_LOG="$BASE_PATH/log/cacti.log"
CACTI_ERRLOG="$BASE_PATH/log/cacti.stderr.log"
APACHE_ERROR="/var/log/apache2/error.log"
APACHE_ACCESS="/var/log/apache2/access.log"
POLLER="$BASE_PATH/poller.php"
WEBUSER="www-data"
DEBUG=0

# ------------------------------------------------------------------------------
# Ensure that the artifact directory is created.  No need for a mess
# ------------------------------------------------------------------------------
if [ ! -d /tmp/check-all-pages ]; then
	mkdir /tmp/check-all-pages
fi

# ------------------------------------------------------------------------------
# Backup the error logs to capture what went wrong
# ------------------------------------------------------------------------------
save_log_files() {
	if [ $started == 1 ];then
		logBase=/tmp/check-all-pages/test.$(date +%s)
		mkdir -p $logBase

		echo "NOTE: Copying $CACTI_LOG to artifacts"
		cp $CACTI_LOG ${logBase}/cacti.log
		cp $CACTI_ERRLOG ${logBase}/cacti_error.log

		if [ -f $APACHE_ACCESS ] ; then
			echo "NOTE: Copying $APACHE_ACCESS to artifacts"
			cp $APACHE_ACCESS ${logBase}/apache_access.log
		fi

		if [ -f $APACHE_ERROR ] ; then
			echo "NOTE: Copying $APACHE_ERROR to artifacts"
			cp -f $APACHE_ERROR ${logBase}/apache_error.log
		fi

		if [ -f $logFile1 ]; then
			echo "NOTE: Copying $logFile1 to artifacts"
			cp -f $logFile1 ${logBase}/wget_error.log
		fi

		chmod a+r ${logBase}/*.log

		if [ $DEBUG -eq 1 ];then
			echo "DEBUG: Dumping $CACTI_LOG"
			cat $CACTI_LOG ${logBase}/cacti.log
			echo "DEBUG: Dumping $CACTI_ERRLOG"
			cat $CACTI_ERRLOG 
			echo "DEBUG: Dumping $APACHE_ACCESS"
			cat $APACHE_ACCESS 
			echo "DEBUG: Dumping $APACHE_ERROR"
			cat $APACHE_ERROR 
		fi
	fi
}

# ------------------------------------------------------------------------------
# Some functions to handle settings consitently
# ------------------------------------------------------------------------------
set_cacti_admin_password() {
	mysql -u"$database_user" -p"$database_pw" -e "UPDATE user_auth SET password=MD5('$login_pw') WHERE id = 1" cacti 2>/dev/null
	mysql -u"$database_user" -p"$database_pw" -e "UPDATE user_auth SET password_change='', must_change_password='' WHERE id = 1" cacti 2>/dev/null
}

enable_log_validation() {
	echo "UPDATE cacti.settings SET value='on' WHERE name='log_validation' ;" | mysql -u"$database_user" -p"$database_pw" cacti 2>/dev/null
}

set_log_level_none() {
	echo "UPDATE cacti.settings SET value='1' WHERE name='log_verbosity' ;" | mysql -u"$database_user" -p"$database_pw" cacti 2>/dev/null
}

set_log_level_normal() {
	echo "UPDATE cacti.settings SET value='2' WHERE name='log_verbosity' ;" | mysql -u"$database_user" -p"$database_pw" cacti 2>/dev/null
}

set_stderr_logging() {
	echo "REPLACE INTO cacti.settings (name, value) VALUES ('path_stderrlog', '$CACTI_ERRLOG');" | mysql -u"$database_user" -p"$database_pw" cacti 2>/dev/null
}

catch_error() {
	echo ""
	echo "WARNING: Process Interrupted.  Exiting ..."

	# Get rid of any jobs
	kill -SIGINT $(jobs -p)

	if [ -f $tmpFile1 ]; then
		rm -f $tmpFile1
	fi

	if [ -f $tmpFile2 ]; then
		rm -f $tmpFile2
	fi

	if [ -f $cookieFile ]; then
		rm -f $cookieFile
	fi

	save_log_files

	exit 0
}

# ------------------------------------------------------------------------------
# To make sure that the autopkgtest/CI sites store the information
# ------------------------------------------------------------------------------
trap 'catch_error' 1 2 3 6 9 14 15

echo "My current directory is `pwd`"

# ------------------------------------------------------------------------------
# Zero out the log files
# ------------------------------------------------------------------------------
> $CACTI_LOG
> $CACTI_ERRLOG
> $APACHE_ERROR
> $APACHE_ACCESS
/bin/chown $WEBUSER:$WEBUSER $CACTI_LOG
/bin/chown $WEBUSER:$WEBUSER $CACTI_ERRLOG


# ------------------------------------------------------------------------------
# Make a backup copy of the Cacti settings table and enable log validation
# ------------------------------------------------------------------------------
set_cacti_admin_password
enable_log_validation
set_stderr_logging

tmpFile1=$(mktemp)
tmpFile2=$(mktemp)
logFile1=$(mktemp)
cookieFile=$(mktemp)
loadSaveCookie="--load-cookies $cookieFile --keep-session-cookies --save-cookies $cookieFile"
started=1

# ------------------------------------------------------------------------------
# Make sure we get the magic, this is stored in the cookies for future use.
# ------------------------------------------------------------------------------
set_log_level_normal

echo "NOTE: Saving Cookie Data"
wget -q --keep-session-cookies --save-cookies "$cookieFile" --output-document="$tmpFile1" http://localhost/cacti/index.php

magic=$(grep "name='__csrf_magic' value=" "$tmpFile1" | sed "s/.*__csrf_magic' value=\"//" | sed "s/\" \/>//")
postData="action=login&login_username=admin&login_password=${login_pw}&__csrf_magic=${magic}"

echo "NOTE: Logging into the Cacti User Interface"
wget -q $loadSaveCookie --post-data="$postData" --output-document="$tmpFile2" http://localhost/cacti/index.php

# ------------------------------------------------------------------------------
# Now loop over all the available links (but don't log out and don't delete or
# remove, don't uninstall, enable or disable plugins stuff.
# ------------------------------------------------------------------------------
echo "NOTE: Recursively Checking all Pages - Note this will take several minutes!!!"
wget $loadSaveCookie --output-file="$logFile1" --reject-regex="(logout\.php|remove|delete|uninstall|install|disable|enable)" --recursive --level=0 --execute=robots=off http://localhost/cacti/index.php
error=$?

if [ $error -eq 8 ]; then
	errors=`grep "awaiting response... 404" $logFile1 | wc -l`
	echo "WARNING: $errors pages not found.  This is not necessarily a bug"
fi

# ------------------------------------------------------------------------------
# Uncomment for debugging.
# ------------------------------------------------------------------------------
#cat $logFile1
#cat $APACHE_ERROR
#cat $APACHE_ACCESS

checks=`grep "HTTP" $logFile1 | wc -l`
echo "NOTE: There were $checks pages checked through recursion"

if [ $DEBUG -eq 1 ];then
	echo ========
	cat $logFile1
	echo ========
fi

if [ $checks -eq 1 ]; then
	echo ========
	cat localhost/cacti/index.php
	echo ========
fi

# ------------------------------------------------------------------------------
# Finally check the cacti log for unexpected items
# ------------------------------------------------------------------------------
echo "NOTE: Checking Cacti Log for Errors"
FILTERED_LOG="$(grep -v \
	-e "AUTH LOGIN: User 'admin' authenticated" \
	-e "WEBUI NOTE: Poller Resource Cache scheduled for rebuild by user admin" \
	-e "IMPORT NOTE: File is Signed Correctly" \
	-e "MAILER INFO:" \
	-e "STATS:" \
	-e "IMPORT Importing XML Data for " \
	-e "CMDPHP SQL Backtrace: " \
	-e "CMDPHP Not Already Set" \
	$CACTI_LOG)" || true

save_log_files

# ------------------------------------------------------------------------------
# Look for errors in the Log
# ------------------------------------------------------------------------------
error=0
if [ -n "${FILTERED_LOG}" ] ; then
    echo "ERROR: Fail - Unexpected output in $CACTI_LOG:"
    echo "${FILTERED_LOG}"
	exit 179
else
    echo "NOTE: Success - No unexpected output in $CACTI_LOG"
	exit 0
fi

