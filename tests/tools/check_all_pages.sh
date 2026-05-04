#!/usr/bin/env bash
#  +-------------------------------------------------------------------------+
#  | Copyright (C) 2004-2026 The Cacti Group                                 |
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

# ------------------------------------------------------------------------------
# Debugging
# ------------------------------------------------------------------------------
#set -xv

# ------------------------------------------------------------------------------
# Restart service and stop the firewall if it's running
# ------------------------------------------------------------------------------
sudo systemctl restart apache2 2>/dev/null
sudo systemctl status apache2 2>/dev/null
sudo systemctl stop firewalld 2>/dev/null

echo "---------------------------------------------------------------------"
echo "NOTE: Check all Pages Script Starting"
echo "---------------------------------------------------------------------"

# ------------------------------------------------------------------------------
# Check for MariaDB or MySQL
# ------------------------------------------------------------------------------
if [ $(which mariadb | wc -l) -gt 0 ]; then
  dbshell="mariadb"
  dbdump="mariadb-dump"
  dbadmin="mariadb-admin"
else
  dbshell="mysql"
  dbdump="mysqldump"
  dbadmin="mysqladmin"
fi

# ------------------------------------------------------------------------------
# Website defaults
# ------------------------------------------------------------------------------
WEBHOST="http://127.0.0.1/cacti";
WAUSER="admin";
WAPASS="admin";

# ------------------------------------------------------------------------------
# Database defaults
# ------------------------------------------------------------------------------
DBFILE="./.my.cnf";
DBHOST="localhost";
DBNAME="cacti";
DBPASS="cacti_user";
DBUSER="cacti_user";
DBSLEEP=2
DBCLIENT=$($dbshell --version | awk '{print $3}')

# ------------------------------------------------------------------------------
# Shell defaults
# ------------------------------------------------------------------------------
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

DEBUG=0
VMSTAT=0

# ------------------------------------------------------------------------------
# Get inputs from user (Interactive mode)
# ------------------------------------------------------------------------------
while [ -n "$1" ]; do
  case $1 in
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
      echo "usage: check_all_pages.sh [--interactive] [options]"
      echo ""
      echo "Options:"
      echo "  --interactive        Prompt for database user/password and Cacti admin password"
      echo "  -wh <url>           Set Cacti web host URL (default: ${WEBHOST})"
      echo "  -wU <user>          Set Cacti web UI username (default: ${WAUSER})"
      echo "  -wp <pass>          Set Cacti web UI password (default: ${WAPASS})"
      echo "  -wo <user>          Set web server user/owner (default: ${WSOWNER})"
      echo "  -we <path>          Set web server error log path (default: ${WSERROR})"
      echo "  -wa <path>          Set web server access log path (default: ${WSACCESS})"
      echo "  -vmstat <seconds>   Provide vmstat output at end of the page run"
      echo "  -debug              Enable debug output"
      echo "  -df <file>          Use database options file and disable DB sleep (default: ${DBFILE})"
      echo "  -dh <host>          Set database host and disable DB sleep (default: ${DBHOST})"
      echo "  -dn <name>          Set database name (default: ${DBNAME})"
      echo "  -du <user>          Set database username and disable DB sleep (default: ${DBUSER})"
      echo "  -dp <pass>          Set database password and disable DB sleep"
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
    "-debug")
      DEBUG=1
      ;;
    "-vmstat")
      if [ -z "$2" ] || ! [[ "$2" =~ ^[0-9]+$ ]]; then
        echo "Error: -vmstat requires a non-negative integer argument." >&2
        exit 1
      fi
      VMSTAT="$2"
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
export MYSQL_AUTH_USR="-u${DBUSER} -p${DBPASS}"
if [ -f "$DBFILE" ]; then
  echo "NOTE: GitHub integration using ${DBFILE}"

  export MYSQL_AUTH_USR="--defaults-file=${DBFILE}"
else
  echo "NOTE: Script is running in batch mode using default credentials!!!"
  if [[ -n "${DBSLEEP}" ]]; then
    sleep "${DBSLEEP}" #Give user a chance to see the prompt
  fi

  export MYSQL_AUTH_USR="-u${DBUSER} -p${DBPASS} -h${DBHOST}"
fi

# --- Get the server version and dump the key variables
DBSERVER=$($dbshell $MYSQL_AUTH_USR -e "SHOW GLOBAL VARIABLES LIKE 'version'" | grep -v Value | awk '{print $2}')

echo "---------------------------------------------------------------------"
echo "Using the following values:";
for v in WEBHOST WAUSER WAPASS DBCLIENT DBSERVER DBFILE DBHOST DBNAME DBPASS DBUSER DBSLEEP WSOWNER WSERROR WSACCESS; do
  name="$v"
  if [[ $name == "WAPASS" || $name == "DBPASS" ]]; then
    value="*******"
  else
    value="${!v}"
  fi

  printf "\t%10s | %s\n" "$name" "$value"
done
echo "---------------------------------------------------------------------"

exec 2>&1

started=0

# ------------------------------------------------------------------------------
# OS Specific Paths
# ------------------------------------------------------------------------------
SCRIPT_PATH=$( cd -- "$( dirname -- "${BASH_SOURCE[0]}" )" &> /dev/null && pwd )
BASE_PATH=$( cd -- "${SCRIPT_PATH}/../../" &> /dev/null && pwd )

echo "NOTE: Base Path is ${BASE_PATH}"

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
      echo "NOTE: Copying ${WSACCESS} to artifacts"
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

  $dbshell $MYSQL_AUTH_USR -e "UPDATE user_auth SET password=MD5('$WAPASS') WHERE id = 1 ;" "$DBNAME"
  $dbshell $MYSQL_AUTH_USR -e "UPDATE user_auth SET password_change='', must_change_password='' WHERE id = 1 ;" "$DBNAME"
  $dbshell $MYSQL_AUTH_USR -e "REPLACE INTO settings (name, value) VALUES ('secpass_forceold', '') ;" "$DBNAME"
}

enable_log_validation() {
  echo "NOTE: Setting Cacti log validation to on to validate improperly validated variables"

  $dbshell $MYSQL_AUTH_USR -e "REPLACE INTO settings (name, value) VALUES ('log_validation','on') ;" "$DBNAME"
}

set_log_level_none() {
  echo "NOTE: Setting Cacti log verbosity to none"

  $dbshell $MYSQL_AUTH_USR -e "REPLACE INTO settings (name, value) VALUES ('log_verbosity', '1') ;" "$DBNAME"
}

set_log_level_normal() {
  echo "NOTE: Setting Cacti log verbosity to low"

  $dbshell $MYSQL_AUTH_USR -e "REPLACE INTO settings (name, value) VALUES ('log_verbosity', '2') ;" "$DBNAME"
}

set_log_level_debug() {
  echo "NOTE: Setting Cacti log verbosity to DEBUG"

  $dbshell $MYSQL_AUTH_USR -e "REPLACE INTO settings (name, value) VALUES ('log_verbosity', '6') ;" "$DBNAME"
}

set_stderr_logging() {
  echo "NOTE: Setting Cacti standard error log location"

  $dbshell $MYSQL_AUTH_USR -e "REPLACE INTO settings (name, value) VALUES ('path_stderrlog', '${CACTI_ERRLOG}');" "$DBNAME"
}

allow_index_following() {
  echo "NOTE: Altering Cacti to allow following pages"

  sed -i "s/<meta name='robots' content='noindex,nofollow'>//g" "$BASE_PATH/lib/html.php"
}

shutdown_handler() {
  echo ""
  echo "WARNING: Process Interrupted.  Cleaning up and Exiting"

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

  if [ -f "/tmp/vmstat.out" ]; then
    rm -f /tmp/vmstat.out
  fi

  save_log_files

  exit 1
}

normal_exit() {
  # Get rid of any jobs
  kill -SIGINT $(jobs -p) 2> /dev/null

  if [ -f "/tmp/vmstat.out" ]; then
    rm -f /tmp/vmstat.out
  fi

  exit $error
}

# ------------------------------------------------------------------------------
# To make sure that the autopkgtest/CI sites store the information
# ------------------------------------------------------------------------------
trap 'shutdown_handler' 1 2 3 6 14 15
trap 'normal_exit' 0

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

# ------------------------------------------------------------------------------
# Check the Apache Syntax and add the default site
# ------------------------------------------------------------------------------
if [ $DEBUG -eq 1 ]; then
  echo "---------------------------------------------------------------------"
  echo "Checking the Apache Config"
  echo "---------------------------------------------------------------------"
  apache2ctl -t
fi

if [ -f "/usr/sbin/a2ensite" -a -f "/etc/apache2/sites-available/000-default.conf" ]; then
  echo "---------------------------------------------------------------------"
  echo "Enabling the Apache Site for Debian/Ubuntu"
  echo "---------------------------------------------------------------------"
  /usr/sbin/a2ensite 000-default.conf 
fi

if [ $DEBUG -eq 1 ]; then
  # ------------------------------------------------------------------------------
  # Check to see if apache2 is up and listening
  # ------------------------------------------------------------------------------
  echo "---------------------------------------------------------------------"
  echo "Network Status showing open Apache ports"
  echo "---------------------------------------------------------------------"
  netstat -anp | grep apache

  # ------------------------------------------------------------------------------
  # Dump the Apache Configuration
  # ------------------------------------------------------------------------------
  if [ -f "/etc/apache2/sites-available/000-default.conf" ]; then
    echo "---------------------------------------------------------------------"
    echo "Apache Configuration for Cacti"
    echo "---------------------------------------------------------------------"
    cat /etc/apache2/sites-available/000-default.conf
  fi

  # ------------------------------------------------------------------------------
  # List to contents of the web root
  # ------------------------------------------------------------------------------
  echo "---------------------------------------------------------------------"
  echo "Top Level Cacti Web Root Files"
  echo "---------------------------------------------------------------------"
  ls -altr /var/www/html/cacti/*.php

  # ------------------------------------------------------------------------------
  # Print out the processlist
  # ------------------------------------------------------------------------------
  echo "---------------------------------------------------------------------"
  echo "Apache Process List"
  echo "---------------------------------------------------------------------"
  ps -ef | grep apache2 | grep -v grep
fi

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
wget -q --keep-session-cookies --save-cookies "$cookieFile" --output-document="$tmpFile1" "$WEBHOST"/index.php >/dev/null 2>&1

if [ -f $tmpFile1 ]; then
  magic=$(grep "name='__csrf_magic' value=" $tmpFile1 | sed "s/.*__csrf_magic' value=\"//" | sed "s/\" \/>//")

  if [ $DEBUG -eq 1 ]; then
    echo "---------------------------------------------------------------------"
    echo "The CSRF Magic Token is"
    echo "---------------------------------------------------------------------"
    echo ${magic}
  fi
else
  echo "---------------------------------------------------------------------"
  echo "FATAL: Unable to locate output file"
  echo "---------------------------------------------------------------------"
  exit 1
fi

postData="action=login&login_username=${WAUSER}&login_password=${WAPASS}&__csrf_magic=${magic}"

echo "NOTE: Logging into the Cacti User Interface"
wget $loadSaveCookie --post-data="${postData}" --output-document="${tmpFile2}" "${WEBHOST}"/index.php >/dev/null 2>&1

if [ $DEBUG -eq 1 ]; then
  echo "---------------------------------------------------------------------"
  echo "Output from index.php"
  echo "---------------------------------------------------------------------"
  cat ${tmpFile2}

  progress=" --show-progress"
else
  progress=""
fi


# ------------------------------------------------------------------------------
# Run vmstat at a frequency of 5 seconds in background
# ------------------------------------------------------------------------------
if [ $VMSTAT -gt 0 ]; then
  vmstat --wide $VMSTAT > /tmp/vmstat.out &
fi

# ------------------------------------------------------------------------------
# Now loop over all the available links (but don't log out and don't delete or
# remove, don't uninstall, enable or disable plugins stuff.
# ------------------------------------------------------------------------------
start_time=$(date +%s)

echo "NOTE: Recursively Checking all Base Pages - Note this will take several minutes!!!"
wget $loadSaveCookie --output-file="${logFile1}" --reject-regex="(logout\.php|remove|delete|uninstall|install|disable|enable)" $progress --recursive --level=0 --execute=robots=off "${WEBHOST}"/index.php >/dev/null 2>&1
error=$?

end_time=$(date +%s)
total=$(($end_time-$start_time))

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

if [ $total -gt 0 ]; then
  check_rate=$(($checks/$total))
else
  check_rate="N/A"
fi

cpus=$(lscpu | grep "CPU(s)" | head -1 | awk '{print $2}')
memory=$(free -g | grep "Mem:" | awk '{print $2}')

echo "NOTE: There were ${checks} pages checked through recursion"
echo "NOTE: Total time was ${total} seconds or ${check_rate} checks per second"
echo "NOTE: Host/Container has ${cpus} CPUs and ${memory} GB of memory"

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
# Output vmstat statistics if requested
# ------------------------------------------------------------------------------
if [ $VMSTAT -gt 0 ]; then
  echo "NOTE: Output of vmstat"
  echo "---------------------------------------------------------------------"
  cat /tmp/vmstat.out
  echo "---------------------------------------------------------------------"
fi

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
  -e "CMDPHP Not Already Set" \
  -e "ieee.org/oui.txt" \
  -e "import_oui_database" \
  -e "PCACHE NOTE" \
  -e "LMSENSORS WARNING" \
  -e "AUTOM8 \[PID:" \
  -e "REINDEX Child" \
  -e "REINDEX Poller" \
  -e "DSDEBUG Bad Data" \
  -e "PUSHOUT Child Started" \
  "$CACTI_LOG")" || true

save_log_files

# ------------------------------------------------------------------------------
# Look for errors in the Log
# ------------------------------------------------------------------------------
error=0
if [ -n "${FILTERED_LOG}" ] ; then
  echo "ERROR: Fail Unexpected output in ${CACTI_LOG}:"
  echo "${FILTERED_LOG}"
  error=179
else
  echo "NOTE: Success No unexpected output in ${CACTI_LOG}"
  error=0
fi
