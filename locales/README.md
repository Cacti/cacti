# Cacti Language Translations

## Summary

Cacti maintains a philosophy of language translation aided by Google and Microsoft
but supported and validated by our users.  Part of the process involves using tools
such as [PO Edit Professional](https://poedit.net/pro/ "PO Edit Professional") to provide hints to strings, and then posting those
prepared language files to our [Cacti Weblate](http://weblate.cacti.net "Cacti Weblate Site") site for users to correct or revise them.

## Files Included in Cacti Translations

The Cacti translations include two major directories that include the human readable and
machine readable versions of the language translation per country and language.  They are:

* <path_cacti>/locales/po - Holds cacti.pot file and various *.po files
* <path_cacti>/locales/LC_MESSAGES - Holds the various *.mo files

## Management Process

When making a pull request, if you are modifying human readable strings, it's 
important that the user modifying those strings to include an updated **cacti.pot** 
file in their pull requests.  To update the **cacti.pot** file, you would simply
follow the directions below:

- Change to the directory where you have found this README.md file
- Look for the script **build_gettext.sh**.  If you find that it not executable, mark it so
- Then run the **build_gettext.sh** script.

This script will perform the following steps:

- Update the cacti.pot file from the entirety of the installed Cacti source directory
- Update your copy of the various language translation files (*.po) per country and language
- Update your copy of the various binary translation files (*.mo) per country and language

The the **build_gettext.sh** script updates both the *.po and *.mo files, those are not important
for your pull requests as Weblate updates those files automatically as soon as it detects
the change in the **cacti.pot** file from your pull request.

## Periodic Mass Language Translations

Periodically, a member of the Cacti Group will take the entirety of the *.po files and uses
PO Edit Professional to perform automated translation of non-translated strings and
then update the Weblate site directly from them.  Those changes will be pushed back to the
Cacti GitHub repository automatically.

## Contributing a new Translation to Cacti

First get yourself a login account at Cacti's Weblate site.  From there, you can request
a language translation be started.  By doing so, this will automatically create a commit 
into the Cacti GitHub repository, which will be a signal to the team.  Once we see the new
translation, we may have a few additional steps that need to be required on the Cacti site.
Someone from the Cacti Development team will reach out to you with those instructions.

Once the Cacti language translation file has been properly on boarded, you can use either
PO Edit or the Weblate sites to make edits to the file.  We find that if you are translating 
a new language, you should first let the Cacti Group do an automatic translation, of if you
want to contribute to PO Edit Professionals development, you can pay for a license to 
facilitate a first pass language translation.

One of the key benefits of using Weblate, is that if you have created additional language
translation files for Cacti plugins, you can translate common strings across all plugins
simultaneously.