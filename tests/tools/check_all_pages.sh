#!/usr/bin/env bash
#  +-------------------------------------------------------------------------+
#  | Copyright (C) 2004-2023 The Cacti Group                                 |
#  |                                                                         |
#  | This program is free software; you can redistribute it and/or           |
#  | modify it under the terms of the GNU General Public License             |
#  | as published by the Free Software Foundation; either version 2          |
#  | of the License, or (at your option) any later version.                  |
#  |                                                                         |
#  | This program is distributed in the hope that it will be useful,         |
#  | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
#  | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
#  | GNU General Public License for more details.                            |
#  +-------------------------------------------------------------------------+
#  | Cacti: The Complete RRDTool-based Graphing Solution                     |
#  +-------------------------------------------------------------------------+
#  | This code is designed, written, and maintained by the Cacti Group. See  |
#  | about.php and/or the AUTHORS file for specific developer information.   |
#  +-------------------------------------------------------------------------+
#  | http://www.cacti.net/                                                   |
#  +-------------------------------------------------------------------------+

# ------------------------------------------------------------------------------
# This script is supposed to test cacti a little bit. At least each page and
# each link is tried. I mean to add checks for new CVE's (at least those that I
# can trigger with wget) as well.
# ------------------------------------------------------------------------------

# Uncomment for debugging
set -x

# On a hunch
sudo systemctl restart apache2
sudo systemctl status apache2

echo "---------------------------------------------------------------------"
echo "NOTE: Check all Pages Script Starting"
echo "---------------------------------------------------------------------"

# --- Website defaults
WEBHOST="http://localhost/cacti";
WAUSER="admin";
WAPASS="admin";

# --- Database defaults
DBFILE="./.my.cnf";
DBHOST="localhost";
DBNAME="cacti";
DBPASS="cacti_user";
DBUSER="cacti_user";
DBSLEEP=2

# --- Shell defaults
WSOWNER="apache"
WSERROR="/var/log/httpd/error_log"
WSACCESS="/var/log/httpd/access_log"

if id www-data > /dev/null 2>&1; then
	WSOWNER="www-data"
	WSERROR="/var/log/apache2/error.log"
	WSACCESS="/var/log/apache2/access.log"
fi

WGET_OUTPUT=$(wget 2>&1);
WGET_RESULT=$?
if [ $WGET_RESULT -eq 127 ]; then
	echo "wget was not found, please install";
	#echo
	#echo "${WGET_OUTPUT}"
	exit 1
fi

while [ -n "$1" ]; do
	case $1 in
# ------------------------------------------------------------------------------
# Get inputs from user (Interactive mode)
# ------------------------------------------------------------------------------
		"--interactive")
			echo "Enter Database username"
			read -r DBUSER
			echo "Enter Database Password"
			read -r DBPASS
			echo "Enter Cacti Admin password"
			read -r WAPASS
			;;
		"--help")
			echo "NOTE: Checks all Cacti pages using wget options"
			echo "NOTE: Original script by team Debian."
			echo ""
			echo "usage: check_all_pages.sh [--interactive]"
			echo ""
			;;
		"-wh")
			WEBHOST="$2"
			shift
			;;
		"-wU")
			WAUSER="$2"
			shift
			;;
		"-wp")
			WAPASS="$2"
			shift
			;;
		"-wo")
			WSOWNER="$2"
			shift
			;;
		"-we")
			WSERROR="$2"
			shift
			;;
		"-wa")
			WSACCESS="$2"
			shift
			;;
		"-df")
			DBFILE="$2"
			DBSLEEP=0
			shift
			;;
		"-dh")
			DBHOST="$2"
			DBSLEEP=0
			shift
			;;
		"-dn")
			DBNAME="$2"
			shift
			;;
		"-du")
			DBUSER="$2"
			DBSLEEP=0
			shift
			;;
		"-dp")
			DBPASS="$2"
			DBSLEEP=0
			shift
			;;
		*)
			;;
	esac
	shift;
done

# --- Website defaults
echo "Using the following values:";
for v in WEBHOST WAUSER WAPASS DBFILE DBHOST DBNAME DBPASS DBUSER DBSLEEP WSOWNER WSERROR WSACCESS; do
	name="$v"
	if [[ $name == "WAPASS" || $name == "DBPASS" ]]; then
		value="*******"
	else
		value="${!v}"
	fi

	printf "\t%10s | %s\n" "$name" "$value"
done

export MYSQL_AUTH_USR="-u${DBUSER} -p${DBPASS}"
if [ -f "$DBFILE" ]; then
    echo "NOTE: GitHub integration using ${DBFILE}"

	export MYSQL_AUTH_USR="--defaults-file=${DBFILE}"
else
	echo "NOTE: Script is running in non-interactive mode ensure you fill out the DB credentials!!!"
	if [[ -n "${DBSLEEP}" ]]; then
		sleep "${DBSLEEP}" #Give user a chance to see the prompt
	fi

	export MYSQL_AUTH_USR="-u${DBUSER} -p${DBPASS} -h${DBHOST}"
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
CACTI_LOG="${BASE_PATH}/log/cacti.log"
CACTI_ERRLOG="${BASE_PATH}/log/cacti.stderr.log"
POLLER="${BASE_PATH}/poller.php"

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
		logBase="/tmp/check-all-pages/test.$(date +%s)"
		mkdir -p "$logBase"

		echo "NOTE: Copying ${CACTI_LOG} to artifacts"
		cp "$CACTI_LOG" "${logBase}/cacti.log"
		cp "$CACTI_ERRLOG" "${logBase}/cacti_error.log"

		if [ -f "$WSACCESS" ] ; then
			echo "NOTE: Copying {$WSACCESS} to artifacts"
			cp "$WSACCESS" "${logBase}/apache_access.log"
		fi

		if [ -f "$WSERROR" ] ; then
			echo "NOTE: Copying ${WSERROR} to artifacts"
			cp -f "$WSERROR" "${logBase}/apache_error.log"
		fi

		if [ -f "$logFile1" ]; then
			echo "NOTE: Copying ${logFile1} to artifacts"
			cp -f "$logFile1" "${logBase}/wget_error.log"
		fi

		chmod a+r -R "${logBase}/"

		if [ $DEBUG -eq 1 ];then
			echo "DEBUG: Dumping ${CACTI_LOG}"
			cat "$CACTI_LOG" "${logBase}/cacti.log"
			echo "DEBUG: Dumping ${CACTI_ERRLOG}"
			cat "${CACTI_ERRLOG}"
			echo "DEBUG: Dumping ${WSACCESS}"
			cat "${WSACCESS}"
			echo "DEBUG: Dumping ${WSERROR}"
			cat "${WSERROR}"
		fi
	fi
}

# ------------------------------------------------------------------------------
# Some functions to handle settings consistently
# ------------------------------------------------------------------------------
set_cacti_admin_password() {
	echo "NOTE: Setting Cacti admin password and unsetting forced password change"

	mysql $MYSQL_AUTH_USR -e "UPDATE user_auth SET password=MD5('$WAPASS') WHERE id = 1 ;" "$DBNAME"
	mysql $MYSQL_AUTH_USR -e "UPDATE user_auth SET password_change='', must_change_password='' WHERE id = 1 ;" "$DBNAME"
	mysql $MYSQL_AUTH_USR -e "REPLACE INTO settings (name, value) VALUES ('secpass_forceold', '') ;" "$DBNAME"
}

enable_log_validation() {
	echo "NOTE: Setting Cacti log validation to on to validate improperly validated variables"

	mysql $MYSQL_AUTH_USR -e "REPLACE INTO settings (name, value) VALUES ('log_validation','on') ;" "$DBNAME"
}

set_log_level_none() {
	echo "NOTE: Setting Cacti log verbosity to none"

	mysql $MYSQL_AUTH_USR -e "REPLACE INTO settings (name, value) VALUES ('log_verbosity', '1') ;" "$DBNAME"
}

set_log_level_normal() {
	echo "NOTE: Setting Cacti log verbosity to low"

	mysql $MYSQL_AUTH_USR -e "REPLACE INTO settings (name, value) VALUES ('log_verbosity', '2') ;" "$DBNAME"
}

set_log_level_debug() {
	echo "NOTE: Setting Cacti log verbosity to DEBUG"

	mysql $MYSQL_AUTH_USR -e "REPLACE INTO settings (name, value) VALUES ('log_verbosity', '6') ;" "$DBNAME"
}

set_stderr_logging() {
	echo "NOTE: Setting Cacti standard error log location"

	mysql $MYSQL_AUTH_USR -e "REPLACE INTO cacti.settings (name, value) VALUES ('path_stderrlog', '${CACTI_ERRLOG}');" "$DBNAME"
}

allow_index_following() {
	echo "NOTE: Altering Cacti to allow following pages"

	sed -i "s/<meta name='robots' content='noindex,nofollow'>//g" "$BASE_PATH/lib/html.php"
}

catch_error() {
	echo ""
	echo "WARNING: Process Interrupted.  Exiting"

	# Get rid of any jobs
	kill -SIGINT $(jobs -p) 2> /dev/null

	if [ -f "$tmpFile1" ]; then
		rm -f "$tmpFile1"
	fi

	if [ -f "$tmpFile2" ]; then
		rm -f "$tmpFile2"
	fi

	if [ -f "$cookieFile" ]; then
		rm -f "$cookieFile"
	fi

	save_log_files

	exit 0
}

# ------------------------------------------------------------------------------
# To make sure that the autopkgtest/CI sites store the information
# ------------------------------------------------------------------------------
trap 'catch_error' 1 2 3 6 9 14 15

echo "NOTE: Current Directory is $(pwd)"

# ------------------------------------------------------------------------------
# Zero out the log files
# ------------------------------------------------------------------------------
> "$CACTI_LOG"
> "$CACTI_ERRLOG"
> "$WSERROR"
> "$WSACCESS"
/bin/chown "$WSOWNER":"$WSOWNER" "$CACTI_LOG"
/bin/chown "$WSOWNER":"$WSOWNER" "$CACTI_ERRLOG"

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
loadSaveCookie="--load-cookies ${cookieFile} --keep-session-cookies --save-cookies ${cookieFile}"
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
wget -q --keep-session-cookies --save-cookies "$cookieFile" --output-document="$tmpFile1" "$WEBHOST"/index.php

magic=$(grep "name='__csrf_magic' value=" "{$tmpFile1}" | sed "s/.*__csrf_magic' value=\"//" | sed "s/\" \/>//")
postData="action=login&login_username=${WAUSER}&login_password=${WAPASS}&__csrf_magic=${magic}"

echo "NOTE: Logging into the Cacti User Interface"
wget -q $loadSaveCookie --post-data="${postData}" --output-document="${tmpFile2}" "${WEBHOST}"/index.php

# ------------------------------------------------------------------------------
# Now loop over all the available links (but don't log out and don't delete or
# remove, don't uninstall, enable or disable plugins stuff.
# ------------------------------------------------------------------------------
echo "NOTE: Recursively Checking all Base Pages - Note this will take several minutes!!!"
wget $loadSaveCookie --output-file="${logFile1}" --reject-regex="(logout\.php|remove|delete|uninstall|install|disable|enable)" --recursive --level=0 --execute=robots=off "${WEBHOST}"/index.php
error=$?

if [ $error -eq 8 ]; then
	errors=$(grep -c "awaiting response... 404" "${logFile1}")
	echo "WARNING: $errors pages not found.  This is not necessarily a bug"
fi

# ------------------------------------------------------------------------------
# Debug Errors if required
# ------------------------------------------------------------------------------
if [ $DEBUG -eq 1 ]; then
	echo "---------------------------------------------------------------------"
	echo "Output of Wget Log file"
	echo "---------------------------------------------------------------------"
	cat "${logFile1}"
	echo "---------------------------------------------------------------------"
	echo "Output of Cacti Log file"
	echo "---------------------------------------------------------------------"
	cat "${CACTI_LOG}"
	echo "---------------------------------------------------------------------"
	echo "Output of Apache Error Log"
	echo "---------------------------------------------------------------------"
	cat "${WSERROR}"
	echo "---------------------------------------------------------------------"
	echo "Output of Apache Access Log"
	echo "---------------------------------------------------------------------"
	cat "${WSACCESS}"
fi

checks=$(grep -c "HTTP" "$logFile1")
echo "NOTE: There were ${checks} pages checked through recursion"

if [[ "${DEBUG}" -eq 1 ]];then
	echo "---------------------------------------------------------------------"
	cat "${logFile1}"
	echo "---------------------------------------------------------------------"
fi

echo "---------------------------------------------------------------------"
echo "NOTE: Displaying some page view statistics for PHP pages only"
echo "---------------------------------------------------------------------"
echo "NOTE: Page                                                     Clicks"
echo "---------------------------------------------------------------------"
awk '{print $7}' < "${WSACCESS}" | awk -F'?' '{print $1}' | grep -v 'index.php' | sort | uniq -c | grep php | awk '{printf("NOTE: %-57s %5d\n", $2, $1)}'
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
	"$CACTI_LOG")" || true

save_log_files

# ------------------------------------------------------------------------------
# Look for errors in the Log
# ------------------------------------------------------------------------------
error=0
if [ -n "${FILTERED_LOG}" ] ; then
    echo "ERROR: Fail Unexpected output in ${CACTI_LOG}:"
    echo "${FILTERED_LOG}"
	exit 179
else
    echo "NOTE: Success No unexpected output in ${CACTI_LOG}"
	exit 0
fi
