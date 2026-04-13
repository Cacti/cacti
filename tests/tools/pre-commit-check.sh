#!/bin/sh
# +-------------------------------------------------------------------------+
# | Pre-commit validation wrapper for Cacti                                |
# | Checks environment and dev tools before running linters.               |
# +-------------------------------------------------------------------------+
set -eu

VENDOR_BIN="include/vendor/bin"
REQUIRED_PHP_MAJOR=8
MAX_PHP_MINOR=4

# ---- Environment checks (run before any tool) ----

check_php_version() {
    php_ver=$(php -r 'echo PHP_MAJOR_VERSION . "." . PHP_MINOR_VERSION;' 2>/dev/null || echo "unknown")
    major=$(echo "$php_ver" | cut -d. -f1)
    minor=$(echo "$php_ver" | cut -d. -f2)

    if [ "$major" != "$REQUIRED_PHP_MAJOR" ]; then
        echo "WARNING: PHP $php_ver detected. Cacti requires PHP 8.x"
    elif [ "$minor" -gt "$MAX_PHP_MINOR" ] 2>/dev/null; then
        echo "WARNING: PHP $php_ver detected. Cacti targets PHP 8.1-8.4."
        echo "  Some type features (true|string) are not available on PHP 8.1."
        echo "  Consider: export PATH=\"/opt/homebrew/opt/php@8.4/bin:\${PATH}\""
    fi
}

check_merge_conflicts() {
    staged=$(git diff --cached --name-only --diff-filter=ACM -- '*.php')

    if [ -z "$staged" ]; then
        return 0
    fi

    conflicts=$(echo "$staged" | xargs grep -l '^<<<<<<<\|^=======$\|^>>>>>>>' 2>/dev/null || true)

    if [ -n "$conflicts" ]; then
        echo ""
        echo "ERROR: Merge conflict markers found in staged files:"
        echo "$conflicts"
        echo "  Resolve conflicts before committing."
        echo ""

        exit 1
    fi
}

check_composer_lock() {
    if git diff --cached --name-only | grep -q '^composer\.lock$'; then
        echo ""
        echo "ERROR: composer.lock is staged for commit."
        echo "  Cacti supports multiple PHP versions; composer.lock must not be committed."
        echo "  Run: git reset HEAD composer.lock"
        echo ""

        exit 1
    fi
}

check_vendor_dev_deps() {
    staged_vendor=$(git diff --cached --name-only | grep '^include/vendor/' | head -5)

    if [ -n "$staged_vendor" ]; then
        echo ""
        echo "WARNING: Vendor files are staged for commit:"
        echo "$staged_vendor"
        echo "  Dev dependencies should not be committed to include/vendor/."
        echo "  Run: git reset HEAD include/vendor/"
        echo ""

        exit 1
    fi
}

check_autoload_freshness() {
    if [ ! -f include/vendor/autoload.php ]; then
        echo "WARNING: your are missing the composer autoload.  Please run composer install"
        exit 1
    fi
}

check_tool() {
    tool="$1"
    label="$2"

    if [ ! -x "$VENDOR_BIN/$tool" ]; then
        echo ""
        echo "ERROR: $label not found at $VENDOR_BIN/$tool"
        echo ""
        echo "  Run: composer install --ignore-platform-reqs"
        echo ""
        echo "  Dev dependencies must be installed for pre-commit hooks."
        echo "  This is a one-time setup after cloning or switching branches."
        echo ""

        exit 1
    fi

    # Verify the tool can actually load (catches missing Symfony/autoload issues)
    if [ $($VENDOR_BIN/$tool --version > /dev/null 2>&1) -gt 0 ]; then
        echo ""
        echo "ERROR: $label exists but failed to load. Autoload may be stale."
        echo ""
        echo "  Run: composer install --ignore-platform-reqs"
        echo ""

        exit 1
    fi
}

# ---- Lint / analysis tools ----

run_lint() {
    if [ -x "$VENDOR_BIN/phplint" ] && [ $("$VENDOR_BIN/phplint" --version > /dev/null 2>&1) -gt 0 ]; then
        echo "Running PHP lint (phplint)..."

        composer run-script lint
    else
        echo "phplint not available or broken, falling back to php -l on staged files..."

        staged=$(git diff --cached --name-only --diff-filter=ACM -- '*.php')

        if [ -z "$staged" ]; then
            echo "  No staged PHP files to check."
            return 0
        fi

        fail=0

        for f in $staged; do
            if ! php -l "$f" > /dev/null 2>&1; then
                php -l "$f"
                fail=1
            fi
        done

        if [ "$fail" -eq 1 ]; then
            exit 1
        fi

        echo "  All staged PHP files pass syntax check."
    fi
}

run_phpcsfixer() {
    check_tool "php-cs-fixer" "PHP CS Fixer"

    echo "Running PHP CS Fixer (dry-run)..."

    if [ $(composer run-script php-cs-fixer) -gt 0 ]; then
        echo ""
        echo "TIP: To auto-fix formatting issues, run:"
        echo "  composer run-script php-cs-fixit"
        echo ""
        exit 1
    fi
}

run_phpstan() {
    check_tool "phpstan" "PHPStan"
    echo "Running PHPStan..."
    composer run-script phpstan
}

# ---- Pre-flight checks (always run) ----

run_preflight() {
    check_php_version
    check_merge_conflicts
    check_composer_lock
    check_vendor_dev_deps
    check_autoload_freshness
}

# ---- Main ----

case "${1:-all}" in
    lint)
        run_preflight
        run_lint
        ;;
    phpcsfixer)
        run_preflight
        run_phpcsfixer
        ;;
    phpstan)
        run_preflight
        run_phpstan
        ;;
    all)
        run_preflight
        run_lint
        run_phpcsfixer
        run_phpstan
        ;;
    preflight)
        run_preflight
        echo "Pre-flight checks passed."
        ;;
    *)
        echo "Usage: $0 {lint|phpcsfixer|phpstan|all|preflight}"
        exit 1
        ;;
esac
