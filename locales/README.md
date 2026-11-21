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
important that the user modifying those strings include an updated **cacti.pot**
file in their pull requests.  To update the **cacti.pot** file, run the
**update-pot.sh** script from the Cacti base directory:

    sh locales/update-pot.sh

This script updates the **cacti.pot** file from the Cacti source and touches
nothing else.  It reads the PHP files of Cacti itself; the `plugins`,
`include/vendor` and `tests` directories are not read.  It needs GNU gettext
(`xgettext`) and `realpath`.

Commit **cacti.pot** and no other file under `locales`.  The language
translation files (*.po) and the binary translation files (*.mo) belong to
Weblate, which updates them as soon as it detects the change in the
**cacti.pot** file from your pull request.

### Checking Your Pull Request

A pull request that changes PHP source or the `locales` directory is checked
by the **i18n Template** workflow.  You can run the same check before you push:

    sh tests/tools/check_message_template.sh

The check does not change your checkout.  It fails when:

- The messages in **cacti.pot** differ from the messages that **update-pot.sh**
  extracts from the source.  The creation date, the source file locations and
  the format flags are not compared, so only an added, removed or changed
  string fails.  Run **update-pot.sh** and commit **cacti.pot** to correct it.
- The **cacti.pot** file or a language translation file does not pass
  `msgfmt --check-format`, for example because a translation changed a `%s`
  placeholder.  Language files that already had such errors when the check was
  introduced are listed in `tests/tools/message_catalog_baseline.txt` and only
  fail when they gain more errors.

### Rebuilding the Language Files

The **build_gettext.sh** script is for maintainers, not for pull requests.  It
performs the following steps:

- Update the cacti.pot file by running **update-pot.sh**
- Update your copy of the various language translation files (*.po) per country and language
- Update your copy of the various binary translation files (*.mo) per country and language

Because it rewrites every language file, do not commit its results in a pull
request that changes code.

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

Once the Cacti language translation file has been properly onboarded, you can use either
PO Edit or the Weblate sites to make edits to the file.  We find that if you are translating 
a new language, you should first let The Cacti Group do an automatic translation, or, if you
want to contribute to PO Edit Professional's development, you can pay for a license to
facilitate a first pass language translation.

One of the key benefits of using Weblate, is that if you have created additional language
translation files for Cacti plugins, you can translate common strings across all plugins
simultaneously.