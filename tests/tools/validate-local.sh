#!/usr/bin/env bash
# validate-local.sh — Run the same checks CI runs, locally, before pushing.
#
# Usage: tests/tools/validate-local.sh [--fix] [--all]
#   --fix  Auto-fix cs-fixer issues instead of dry-run
#   --all  Check entire repo (default: only files changed vs upstream/develop)
#
# Requires: PHP 8.4 (brew install php@8.4), codespell (brew install codespell)

set -euo pipefail

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

# Find PHP 8.4 (or any 8.x that works)
find_php() {
    local candidates=(
        /opt/homebrew/opt/php@8.4/bin/php
        /opt/homebrew/Cellar/php@8.4/8.4.19/bin/php
        /opt/homebrew/Cellar/php@8.4/8.4.18/bin/php
    )
    for c in "${candidates[@]}"; do
        [[ -x "$c" ]] && echo "$c" && return
    done
    if php -r 'exit(PHP_MAJOR_VERSION==8 && PHP_MINOR_VERSION<=4 ? 0 : 1);' 2>/dev/null; then
        echo "php"
        return
    fi
    return 1
}

PHP=$(find_php) || { echo -e "${RED}PHP 8.1-8.4 not found. Install with: brew install php@8.4${NC}"; exit 1; }
echo -e "${YELLOW}Using PHP: $($PHP -r 'echo PHP_VERSION;')${NC}"

FIX_MODE=""
CHECK_ALL=""
for arg in "$@"; do
    case "$arg" in
        --fix) FIX_MODE="yes" ;;
        --all) CHECK_ALL="yes" ;;
    esac
done

FAIL=0
ROOT="$(git rev-parse --show-toplevel)"
cd "$ROOT"

# Determine base branch for diff
BASE="upstream/develop"
if ! git rev-parse --verify "$BASE" &>/dev/null; then
    BASE="origin/develop"
fi
if ! git rev-parse --verify "$BASE" &>/dev/null; then
    BASE="develop"
fi

# Get changed PHP files
if [[ -n "$CHECK_ALL" ]]; then
    CHANGED_PHP=()
else
    mapfile -t CHANGED_PHP < <(git diff --name-only "$BASE"...HEAD -- '*.php' 2>/dev/null || true)
fi

echo -e "${YELLOW}Changed PHP files: ${#CHANGED_PHP[@]}${NC}"

# Ensure vendor is installed
if ! $PHP -r "require 'include/vendor/autoload.php';" 2>/dev/null; then
    echo -e "${YELLOW}Installing composer dependencies...${NC}"
    $PHP "$(command -v composer)" install --no-interaction --quiet
fi

# 1. Codespell (on changed files only, unless --all)
echo -n "[1/5] Spell check... "
if command -v codespell &>/dev/null; then
    if [[ -n "$CHECK_ALL" ]]; then
        if codespell --config .codespell.cfg 2>/dev/null; then
            echo -e "${GREEN}PASS${NC}"
        else
            echo -e "${RED}FAIL${NC}"
            FAIL=1
        fi
    else
        CHANGED_ALL=()
        mapfile -t CHANGED_ALL < <(git diff --name-only "$BASE"...HEAD 2>/dev/null || true)
        if [[ ${#CHANGED_ALL[@]} -eq 0 ]]; then
            echo -e "${GREEN}SKIP (no changes)${NC}"
        else
            if codespell --config .codespell.cfg "${CHANGED_ALL[@]}" 2>/dev/null; then
                echo -e "${GREEN}PASS${NC}"
            else
                echo -e "${RED}FAIL${NC}"
                FAIL=1
            fi
        fi
    fi
else
    echo -e "${YELLOW}SKIP (codespell not installed; brew install codespell)${NC}"
fi

# 2. PHP Lint (changed files only)
echo -n "[2/5] PHP lint... "
if [[ -n "$CHECK_ALL" ]]; then
    if $PHP include/vendor/bin/phplint --no-cache --exclude=include/vendor 2>/dev/null; then
        echo -e "${GREEN}PASS${NC}"
    else
        echo -e "${RED}FAIL${NC}"
        FAIL=1
    fi
elif [[ ${#CHANGED_PHP[@]} -eq 0 ]]; then
    echo -e "${GREEN}SKIP (no PHP changes)${NC}"
else
    LINT_FAIL=0
    for f in "${CHANGED_PHP[@]}"; do
        [[ -f "$f" ]] || continue
        if ! $PHP -l "$f" &>/dev/null; then
            echo ""
            $PHP -l "$f"
            LINT_FAIL=1
        fi
    done
    if [[ $LINT_FAIL -eq 0 ]]; then
        echo -e "${GREEN}PASS${NC}"
    else
        echo -e "${RED}FAIL${NC}"
        FAIL=1
    fi
fi

# 3. PHP CS Fixer (changed files only)
echo -n "[3/5] CS Fixer... "
if [[ -n "$CHECK_ALL" ]]; then
    CS_ARGS=""
else
    CS_ARGS=""
    for f in "${CHANGED_PHP[@]}"; do
        [[ -f "$f" ]] && CS_ARGS="$CS_ARGS -- $f"
    done
fi
if [[ -n "$FIX_MODE" ]]; then
    if [[ -n "$CS_ARGS" ]]; then
        $PHP include/vendor/bin/php-cs-fixer fix --allow-unsupported-php-version=yes --config=./.php-cs-fixer.php $CS_ARGS 2>/dev/null
    else
        $PHP include/vendor/bin/php-cs-fixer fix --allow-unsupported-php-version=yes --config=./.php-cs-fixer.php 2>/dev/null
    fi
    echo -e "${GREEN}FIXED${NC}"
else
    if [[ ${#CHANGED_PHP[@]} -eq 0 && -z "$CHECK_ALL" ]]; then
        echo -e "${GREEN}SKIP (no PHP changes)${NC}"
    elif [[ -n "$CS_ARGS" ]]; then
        if $PHP include/vendor/bin/php-cs-fixer fix --allow-unsupported-php-version=yes --dry-run --diff --config=./.php-cs-fixer.php $CS_ARGS 2>/dev/null; then
            echo -e "${GREEN}PASS${NC}"
        else
            echo -e "${RED}FAIL${NC} (run with --fix to auto-fix)"
            FAIL=1
        fi
    else
        if $PHP include/vendor/bin/php-cs-fixer fix --allow-unsupported-php-version=yes --dry-run --diff --config=./.php-cs-fixer.php 2>/dev/null; then
            echo -e "${GREEN}PASS${NC}"
        else
            echo -e "${RED}FAIL${NC} (run with --fix to auto-fix)"
            FAIL=1
        fi
    fi
fi

# 4. PHPStan (always runs full analysis; it needs whole-project context)
echo -n "[4/5] PHPStan... "
if $PHP include/vendor/bin/phpstan analyse --level 6 2>/dev/null; then
    echo -e "${GREEN}PASS${NC}"
else
    echo -e "${RED}FAIL${NC}"
    FAIL=1
fi

# 5. Pest tests
echo -n "[5/5] Pest tests... "
if $PHP include/vendor/bin/pest --display-warnings 2>/dev/null; then
    echo -e "${GREEN}PASS${NC}"
else
    echo -e "${RED}FAIL${NC}"
    FAIL=1
fi

echo ""
if [[ $FAIL -eq 0 ]]; then
    echo -e "${GREEN}All checks passed. Safe to push.${NC}"
else
    echo -e "${RED}Some checks failed. Fix before pushing.${NC}"
    exit 1
fi
