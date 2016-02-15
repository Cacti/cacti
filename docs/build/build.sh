#/bin/sh

###########################################################################
# Script to build documentationd
#
# Copyright (C) 2004-2016 The Cacti Group
###########################################################################

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
for FILE in cacti.dsl manual.sgml
do
	if [ ! -e "${FILE}" ]
	then
		echo "ERROR: Required file ${FILE} absent, unable to proceed with build"
		echo "ERROR: Make sure your current directory is the docs/build"
		exit 1
	fi
done
if [ ! -d "images" ]
then
	echo "ERROR: Required images directory absent, unable to proceed with build"
	echo "ERROR: Make sure your current directory is the docs/build"
	exit 1
fi

# Generate TXT documentation
echo "Generating TXT documentation..."
docbook2txt --dsl cacti.dsl#html2txt --output txt manual.sgml 1>/dev/null 2>&1
if [ $? -gt 0 ]
then
	echo "ERROR: Failed to generate TXT documentation because"
	exit 1
fi

# Generate HTML documentation
echo "Generating HTML documentation..."
docbook2html --dsl cacti.dsl#html --output html manual.sgml 1>/dev/null 2>&1
if [ $? -gt 0 ]
then
	echo "ERROR: Failed to generate HTML documentation because"
	exit 1
fi

# Generate PDF documentation
echo "Generating PDF documentation..."
docbook2pdf --dsl cacti.dsl#print --output pdf manual.sgml 1>/dev/null 2>&1
if [ $? -gt 0 ]
then
	echo "ERROR: Failed to generate PDF documentation because"
	exit 1
fi

echo "Generation of documentation completed successfully"
exit 0
