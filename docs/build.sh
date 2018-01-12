#/bin/sh

###########################################################################
# Script to build documentationd
#
# Copyright (C) 2004-2018 The Cacti Group
###########################################################################

# Set distro option - removes build sources
DISTRO="NO"
if [ "${1}" == "--distro" ]
then
	DISTRO="YES"
fi

# Check for needed programs
for PROG in docbook2txt docbook2html docbook2pdf
do
	which ${PROG} 1>/dev/null 2>&1
	if [ $? -gt 0 ]
	then
		echo "ERROR: Please install ${PROG} so that documentation can be built"
		exit 1
	fi
done

# Script assumes current working directory (CWD) is the docs/build directory
# from source. Make sure required files and directories exist
for FILE in build/cacti.dsl build/manual.sgml
do
	if [ ! -e "${FILE}" ]
	then
		echo "ERROR: Required file ${FILE} absent, unable to proceed with build"
		echo "ERROR: Make sure your current directory is the docs/build"
		exit 1
	fi
done
if [ ! -d "build/images" ]
then
	echo "ERROR: Required images directory absent, unable to proceed with build"
	echo "ERROR: Make sure your current directory is the docs/build"
	exit 1
fi

# Generate TXT documentation
echo "Generating TXT documentation..."
docbook2txt --dsl build/cacti.dsl#html2txt --output txt build/manual.sgml 1>/dev/null 2>&1
if [ $? -gt 0 ]
then
	echo "ERROR: Failed to generate TXT documentation"
	exit 1
fi

# Generate HTML documentation
echo "Generating HTML documentation..."
docbook2html --dsl build/cacti.dsl#html --output html build/manual.sgml 1>/dev/null 2>&1
if [ $? -gt 0 ]
then
	echo "ERROR: Failed to generate HTML documentation"
	exit 1
fi
cp -R build/images html/ 1>/dev/null 2>&1
if [ $? -gt 0 ]
then
	echo "ERROR: Failed to copy images into HTML documentation"
	exit 1
fi

# Generate PDF documentation
echo "Generating PDF documentation..."
docbook2pdf --dsl build/cacti.dsl#print --output pdf build/manual.sgml 1>/dev/null 2>&1
if [ $? -gt 0 ]
then
	echo "ERROR: Failed to generate PDF documentation"
	exit 1
fi

# Remove build stuff if distro option set
if [ "${DISTRO}" == "YES" ]
then
	rm -Rf build build.sh
fi

echo "Generation of documentation completed successfully"
exit 0
