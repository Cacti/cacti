# Paper Plane theme validation

Paper Plane keeps wrapped titles beside their controls while preserving short
25px bars. Plain dark tables use light text and links; alternating light rows
and grey selections use darker colors. White jQuery UI dialogs inherit their
readable text color and use darker links. Hover rules keep selected links dark and
make links readable when a selectable row turns dark. Table headings use a
lighter grey against their dark gradient.

## Populated browser fixtures

The browser loads the shipped Paper Plane and jQuery UI stylesheets. The fixture
uses native `cactiTable` content and alternating row classes. Device names,
response times, traffic, and alert messages are synthetic monitoring data.
These fixtures do not require a running Cacti installation.

![Paper Plane device inventory with synthetic measurements](images/paper-plane-theme-desktop.png)

The browser follows a device link, opens a real jQuery UI details dialog at 320px,
checks its alert data and title bounds, and closes it.

![Paper Plane narrow details with synthetic alerts](images/paper-plane-theme-mobile-details.png)

## Reproduce

Use the dependency setup in [Cacti theme validation](cacti-theme.md), then run
from `tests/e2e`:

```sh
CACTI_THEME_COVERAGE=1 mise exec node@22 -- node node_modules/@playwright/test/cli.js test \
  tests/develop-theme-correctness.spec.ts tests/develop-theme-cleanup.spec.ts --project=chromium
```

Set `UPDATE_THEME_SCREENSHOTS=1` to regenerate screenshots. Tests cover native
title spans and controls at 320, 390, and 1280px, links and plain text across row states,
selected tabs, navigation, and active controls. Table headings are checked in
normal, selected, and hovered-column states. Contrast checks compose alpha
and sample sRGB gradient stops and interiors. They do not prove contrast for
arbitrary image backgrounds or every pixel.

After committing, measure changed CSS line coverage from the repository root:

```sh
mise exec python@3.12 -- python tests/tools/report_changed_coverage.py \
  --base fix/sunrise-theme-accessibility --language css --minimum 100 \
  --browser-root tests/e2e/test-results
```

This layer extends the shared browser helpers above Sunrise in the native theme
stack. Use `origin/develop` after the lower layers merge. The gate measures
executed changed CSS lines, not whole-project or branch coverage.
