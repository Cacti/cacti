#!/usr/bin/env bash
update_copyright() {
	local file=$1
	file=${file/$SCRIPT_BASE/};
	printf -v line "%60s" "$file"
	if [[ -z "$ERRORS_ONLY" ]]; then
		echo -n "$line"
		line=
	fi

	old_data=$(grep -e "2004-20[0-9][0-9]" "$1" 2>/dev/null)
	result=$?

	if [[ $result -eq 0 ]]; then
		if [[ $old_data =~ -${YEAR} ]]; then
			if [[ -z "$ERRORS_ONLY" ]]; then
				echo "$line Skipping Copyright Data"
			fi
		else
			new_data=${old_data// 2004-20[0-9][0-9] / 2004-$YEAR }
			echo "$line Updating Copyright Data"
			printf "\tOld: %s\n\tNew: %s\n\n" "$old_data" "$new_data"
			sed -i s/"$old_data"/"$new_data"/g $1
		fi
	else
		echo "$line  Copyright not found!"
		SCRIPT_ERR=1
	fi
}

SCRIPT_DIR=$( cd -- "$( dirname -- "${BASH_SOURCE[0]}" )" &> /dev/null && pwd )
SCRIPT_BASE=$(realpath "${SCRIPT_DIR}/../../")/

BAD_FOLDERS="include/vendor \*\*/vendor include/fa cache include/js"
SCRIPT_EXCLUSION=
for f in $BAD_FOLDERS; do
	SCRIPT_EXCLUSION="$SCRIPT_EXCLUSION -not -path ${SCRIPT_BASE}$f/\* "
done

SCRIPT_ERR=0
YEAR=$(date +"%Y")

ERRORS_ONLY=1
while [ -n "$1" ]; do
	case $1 in
# ------------------------------------------------------------------------------
# Get inputs from user (Interactive mode)
# ------------------------------------------------------------------------------
		"--help")
			echo "NOTE: Checks all Cacti pages for this years copyright"
			echo ""
			echo "usage: copyright_year.sh [-a]"
			echo ""
			;;
		"-A"|"-a")
			ERRORS_ONLY=0
			;;
		*)
			;;
	esac
	shift;
done

# ----------------------------------------------
# PHP / JS / MD Files
# ----------------------------------------------
SCRIPT_INCLUSION=
SCRIPT_SEPARATOR=
for ext in sql php js md; do
	if [ -n "$SCRIPT_INCLUSION" ]; then
		SCRIPT_SEPARATOR="-o "
	fi
	SCRIPT_INCLUSION="$SCRIPT_INCLUSION $SCRIPT_SEPARATOR-name \*.$ext"
done

SCRIPT_CMD="find ${SCRIPT_BASE} -type f \( $SCRIPT_INCLUSION \) $SCRIPT_EXCLUSION -print0"
bash -c "$SCRIPT_CMD" | while IFS= read -r -d '' file; do
	update_copyright "${file}"
done
