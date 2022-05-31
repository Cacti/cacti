#!/bin/bash
# ------------------------------------------------------------------------------
# This script is supposed to test cacti a little bit. At least each page and
# each link is tried. I mean to add checks for new CVE's (at least those that I
# can trigger with wget) as well.
# ------------------------------------------------------------------------------

mode=$1

echo "---------------------------------------------------------------------"
echo "NOTE: Check all Pages Script Starting"
echo "---------------------------------------------------------------------"

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

	export MYSQL_AUTH_USR="-ucactiuser -pcactiuser"
elif [ "$mode" = "--help" ]; then
	echo "NOTE: Checks all Cacti pages using wget options"
	echo "NOTE: Original script by team Debian."
	echo ""
	echo "usage: check_all_pages.sh [--interactive]"
	echo ""
elif [ -f ./.my.cnf ]; then
    echo "NOTE: GitHub integration using ./.my.cnf.cnf"

	export MYSQL_AUTH_USR="--defaults-file=./.my.cnf"
	login_pw="admin"
else
	echo "NOTE: Script is running in non-interactive mode ensure you fill out the DB credentials!!!"
	sleep 2 #Give user a chance to see the prompt

	export MYSQL_AUTH_USR="-ucactiuser -pcactiuser -hlocalhost"
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

echo "NOTE: Base Path is ${BASE_PATH}"

DEBUG=0
CACTI_LOG="$BASE_PATH/log/cacti.log"
CACTI_ERRLOG="$BASE_PATH/log/cacti.stderr.log"
POLLER="$BASE_PATH/poller.php"

if id www-data > /dev/null 2>&1;then
  WEBUSER="www-data"
  APACHE_ERROR="/var/log/apache2/error.log"
  APACHE_ACCESS="/var/log/apache2/access.log"
else
  WEBUSER="apache"
  APACHE_ERROR="/var/log/httpd/error_log"
  APACHE_ACCESS="/var/log/httpd/access_log"
fi

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
	echo "---------------------------------------------------------------------"
	echo "Saving All Log Files"
	echo "---------------------------------------------------------------------"

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
# Some functions to handle settings consistently
# ------------------------------------------------------------------------------
set_cacti_admin_password() {
	echo "NOTE: Setting Cacti admin password and unsetting forced password change"

	mysql $MYSQL_AUTH_USR -e "UPDATE user_auth SET password=MD5('$login_pw') WHERE id = 1 ;" cacti 
	mysql $MYSQL_AUTH_USR -e "UPDATE user_auth SET password_change='', must_change_password='' WHERE id = 1 ;" cacti 
	mysql $MYSQL_AUTH_USR -e "REPLACE INTO settings (name, value) VALUES ('secpass_forceold', '') ;" cacti 
}

enable_log_validation() {
	echo "NOTE: Setting Cacti log validation to on to validate improperly validated variables"

	mysql $MYSQL_AUTH_USR -e "REPLACE INTO settings (name, value) VALUES ('log_validation','on') ;" cacti
}

set_log_level_none() {
	echo "NOTE: Setting Cacti log verbosity to none"

	mysql $MYSQL_AUTH_USR -e "REPLACE INTO settings (name, value) VALUES ('log_verbosity', '1') ;" cacti
}

set_log_level_normal() {
	echo "NOTE: Setting Cacti log verbosity to low"

	mysql $MYSQL_AUTH_USR -e "REPLACE INTO settings (name, value) VALUES ('log_verbosity', '2') ;" cacti
}

set_log_level_debug() {
	echo "NOTE: Setting Cacti log verbosity to DEBUG"

	mysql $MYSQL_AUTH_USR -e "REPLACE INTO settings (name, value) VALUES ('log_verbosity', '6') ;" cacti
}

set_stderr_logging() {
	echo "NOTE: Setting Cacti standard error log location"

	mysql $MYSQL_AUTH_USR -e "REPLACE INTO cacti.settings (name, value) VALUES ('path_stderrlog', '$CACTI_ERRLOG');" cacti
}

allow_index_following() {
	echo "NOTE: Altering Cacti to allow following pages"

	sed -i "s/<meta name='robots' content='noindex,nofollow'>//g" $BASE_PATH/lib/html.php
}

catch_error() {
	echo ""
	echo "WARNING: Process Interrupted.  Exiting"

	# Get rid of any jobs
	kill -SIGINT $(jobs -p) 2> /dev/null

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

echo "NOTE: Current Directory is `pwd`"

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
allow_index_following

tmpFile1=$(mktemp)
tmpFile2=$(mktemp)
logFile1=$(mktemp)
cookieFile=$(mktemp)
loadSaveCookie="--load-cookies $cookieFile --keep-session-cookies --save-cookies $cookieFile"
started=1

# ------------------------------------------------------------------------------
# Make sure we get the magic, this is stored in the cookies for future use.
# ------------------------------------------------------------------------------
if [ $DEBUG -eq 1 ]; then
	set_log_level_debug
else
	set_log_level_normal
fi

echo "---------------------------------------------------------------------"
echo "Starting Web Based Page Validation"
echo "---------------------------------------------------------------------"
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
echo "NOTE: Recursively Checking all Base Pages - Note this will take several minutes!!!"
wget $loadSaveCookie --output-file="$logFile1" --reject-regex="(logout\.php|remove|delete|uninstall|install|disable|enable)" --recursive --level=0 --execute=robots=off http://localhost/cacti/index.php
error=$?

if [ $error -eq 8 ]; then
	errors=`grep "awaiting response... 404" $logFile1 | wc -l`
	echo "WARNING: $errors pages not found.  This is not necessarily a bug"
fi

# ------------------------------------------------------------------------------
# Debug Errors if required
# ------------------------------------------------------------------------------
if [ $DEBUG -eq 1 ]; then
	echo "---------------------------------------------------------------------"
	echo "Output of Wget Log file"
	echo "---------------------------------------------------------------------"
	cat $logFile1
	echo "---------------------------------------------------------------------"
	echo "Output of Cacti Log file"
	echo "---------------------------------------------------------------------"
	cat $CACTI_LOG
	echo "---------------------------------------------------------------------"
	echo "Output of Apache Error Log
	echo "---------------------------------------------------------------------"
	cat $APACHE_ERROR
	echo "---------------------------------------------------------------------"
	echo "Output of Apache Access Log
	echo "---------------------------------------------------------------------"
	cat $APACHE_ACCESS
fi

checks=`grep "HTTP" $logFile1 | wc -l`
echo "NOTE: There were $checks pages checked through recursion"

if [ $DEBUG -eq 1 ];then
	echo "---------------------------------------------------------------------"
	cat $logFile1
	echo "---------------------------------------------------------------------"
fi

echo "---------------------------------------------------------------------"
echo "NOTE: Displaying some page view statistics for PHP pages only"
echo "---------------------------------------------------------------------"
echo "NOTE: Page                                                     Clicks"
echo "---------------------------------------------------------------------"
cat $APACHE_ACCESS | awk '{print $7}' | awk -F'?' '{print $1}' | grep -v 'index.php' | sort | uniq -c | grep php | awk '{printf("NOTE: %-57s %5d\n", $2, $1)}'
echo "---------------------------------------------------------------------"

# ------------------------------------------------------------------------------
# Finally check the cacti log for unexpected items
# ------------------------------------------------------------------------------
echo "NOTE: Checking Cacti Log for Errors"
FILTERED_LOG="$(grep -v \
	-e "AUTH LOGIN: User 'admin' authenticated" \
	-e "WEBUI NOTE: Poller Resource Cache scheduled for rebuild by user admin" \
	-e "WEBUI NOTE: Poller Cache repopulated by user admin" \
	-e "WEBUI NOTE: Cacti DS Stats purged by user admin" \
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
    echo "ERROR: Fail Unexpected output in $CACTI_LOG:"
    echo "${FILTERED_LOG}"
	exit 179
else
    echo "NOTE: Success No unexpected output in $CACTI_LOG"
	exit 0
fi

