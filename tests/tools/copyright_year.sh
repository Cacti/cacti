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

SCRIPT_DIR=$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" &>/dev/null && pwd)
SCRIPT_BASE=$(realpath "${SCRIPT_DIR}/../../")/
SCRIPT_ERR=0

update_copyright() {
	local file=$1
	file=${file/$SCRIPT_BASE/}
	printf -v line "%60s" "$file"
	if [[ -z "$ERRORS_ONLY" ]]; then
		echo -n "$line"
		line=
	fi

	# Only rewrite years on Copyright lines; other ranges such as "lines
	# 2041-2056" are data. grep -E and sed -E share one ERE syntax on GNU and BSD.
	old_reg="(Copyright.*)20[0-9][0-9] *- *20[0-9][0-9]"
	old_data=$(grep -c -E -e "$old_reg" "$1" 2>/dev/null)
	new_reg="\12004-$YEAR"

	if [[ $old_data -eq 0 ]]; then
		old_reg="(Copyright.*) 20[0-9][0-9] "
		old_data=$(grep -c -E -e "$old_reg" "$1" 2>/dev/null)
		new_reg="\1 2004-$YEAR "
	fi

	if [[ $old_data -gt 0 ]]; then
		old_data=$(grep -E -e "$old_reg" "$1" 2>/dev/null)
		new_data=$(echo "$old_data" | sed -E "s/$old_reg/$new_reg/")
		if [[ "$old_data" == "$new_data" ]]; then
			if [[ -z "$ERRORS_ONLY" ]]; then
				echo "$line Skipping Copyright Data"
			fi
		else
			echo "$line Updating Copyright Data"
			printf "%60s %s\n" "==============================" "===================="
			printf "%60s %s\n" "$old_data" "=>"
			printf "%60s %s\n" "$new_data" ""
			# sed -i takes different arguments on GNU and BSD. Rewrite through a
			# temporary file and copy back so the original mode is kept.
			tmp=$(mktemp)
			sed -E "s/$old_reg/$new_reg/" "$1" > "$tmp" && cat "$tmp" > "$1"
			rm -f "$tmp"
			printf "%60s %s\n" "==============================" "===================="
		fi
	else
		echo "$line  Copyright not found!"
		SCRIPT_ERR=1
	fi
}

scan_folders() {
	SCRIPT_INCLUSION=
	SCRIPT_SEPARATOR=
	for ext in $1; do
		if [ -n "$SCRIPT_INCLUSION" ]; then
			SCRIPT_SEPARATOR="-o "
		fi
		SCRIPT_INCLUSION="$SCRIPT_INCLUSION $SCRIPT_SEPARATOR-name \*.$ext"
	done

	for f in $2; do
		if [ -n "$SCRIPT_INCLUSION" ]; then
			SCRIPT_SEPARATOR="-o "
		fi
		SCRIPT_INCLUSION="$SCRIPT_INCLUSION $SCRIPT_SEPARATOR-name $f"
	done

	# Group the -o alternatives; otherwise -print0 binds to the last -name only.
	if [ -n "$SCRIPT_INCLUSION" ]; then
		SCRIPT_INCLUSION="\( $SCRIPT_INCLUSION \)"
	fi

	SCRIPT_SEPARATOR=
	FOLDER_INCLUSION=
	for f in $3; do
		if [ -n "$FOLDER_INCLUSION" ]; then
			SCRIPT_SEPARATOR="-o "
		fi

		FOLDER_INCLUSION="$FOLDER_INCLUSION $SCRIPT_SEPARATOR-path ${SCRIPT_BASE}$f/\*"
	done

	if [[ -n "$FOLDER_INCLUSION" ]]; then
		SCRIPT_SEPARATOR=
		if [ -n "$SCRIPT_INCLUSION" ]; then
			SCRIPT_SEPARATOR="-a \( $SCRIPT_INCLUSION \)"
		fi
		SCRIPT_INCLUSION="\( $FOLDER_INCLUSION $SCRIPT_SEPARATOR \)"
	fi

	SCRIPT_SEPARATOR=
	SCRIPT_EXCLUSION=
	for f in $4; do
		if [ -n "$SCRIPT_EXCLUSION" ]; then
			SCRIPT_SEPARATOR="-o "
		fi
		SCRIPT_EXCLUSION="$SCRIPT_EXCLUSION $SCRIPT_SEPARATOR-path ${SCRIPT_BASE}$f/\*"
	done

	for f in $5; do
		if [ -n "$SCRIPT_EXCLUSION" ]; then
			SCRIPT_SEPARATOR="-o "
		fi
		SCRIPT_EXCLUSION="$SCRIPT_EXCLUSION $SCRIPT_SEPARATOR-name $f"
	done

	if [[ -n "$SCRIPT_EXCLUSION" ]]; then
		SCRIPT_EXCLUSION="-not \( $SCRIPT_EXCLUSION \)"
	fi

	SCRIPT_CMD="find ${SCRIPT_BASE} -type f $SCRIPT_INCLUSION $SCRIPT_EXCLUSION -print0"
	# Read from process substitution, not a pipe, so SCRIPT_ERR set by
	# update_copyright survives in this shell.
	while IFS= read -r -d '' file; do
		update_copyright "${file}"
	done < <(bash -c "$SCRIPT_CMD")

}

YEAR=$(date +"%Y")
EXC_FOLDERS=".git .vscode images include/vendor include/themes/\*/vendor include/themes/\*/default include/themes/\*/images vendor fonts include/fonts include/fa include/js plugins/\*/.git plugins/\*/include/vendor plugins/\*/Net plugins/\*/lib/Doctrine"
EXC_FILES="LICENSE \*.rrd \*.cache \*.ttf \*.pdf \*.jpg \*.jpeg \*.csv c3.css pace.css billboard.css .rnd Diff.css \*.png \*.gif jquery\* colors.csv \*.xml.gz \*.format cacti_version \*.log\* \*.mo \*.po \*.pot \*.xml"
INC_EXTENSIONS=""
INC_FOLDERS=""
ERRORS_ONLY=1
while [ -n "$1" ]; do
	case $1 in
	"--help")
		echo "NOTE: Checks all Cacti pages for this years copyright"
		echo ""
		echo "usage: copyright_year.sh [-a]"
		echo ""
		;;
	"-S" | "-s")
		shift
		EXC_FILES="$1"
		;;
	"-E" | "-e")
		shift
		INC_EXTENSIONS="$1"
		;;
	"-F" | "-f")
		shift
		INC_FOLDERS="$1"
		;;
	"-A" | "-a")
		ERRORS_ONLY=
		echo "Searching..."
		;;
	*) echo "Bad option - $1";;

	esac
	shift
done

# ----------------------------------------------
# PHP / JS / MD Files
# ----------------------------------------------

scan_folders "$INC_EXTENSIONS" "" "$INC_FOLDERS" "$EXC_FOLDERS" "$EXC_FILES"
scan_folders "" ".htaccess index.php" "$INC_FOLDERS" "" ""

exit $SCRIPT_ERR
