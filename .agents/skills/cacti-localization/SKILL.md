---
name: cacti-localization
description: Implement or review translatable Cacti UI text, plugin gettext domains, JavaScript strings and right-to-left layouts.
---

Trace the message from its PHP or JavaScript call site to its catalog domain, compiled translation, and final rendering context. For a plugin, verify the catalog name and domain used by that plugin. Read the target branch's [language helpers](../../../include/global_languages.php) and use the repository's extraction and compilation workflow when changing catalogs.

Use a translation that visibly differs from the source. Where the message takes parameters, reorder two distinct values with positional placeholders and include a literal percent sign. For text passed into JavaScript or an HTML attribute, include punctuation that exercises that context and check the value the browser actually receives. The [gettext runtime tests](../../../tests/Unit/Core/I18n/GlobalLanguagesRuntimeTest.php) and [translated JavaScript tests](../../../tests/Unit/Ui/Html/TranslatedScriptStringEncodingTest.php) exercise different stages.

Render the affected page in a translated locale. For layout changes, switch between an RTL and an LTR locale in the same theme and inspect direction, control placement and label readability. Report the locale, domain, rendered value and browser observation. A successful catalog build establishes that the catalog compiles; selecting it at runtime and displaying its content are separate checks.
