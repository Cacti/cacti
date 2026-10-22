#!/usr/bin/env bash
# check-i18n.sh - Pre-commit hook for i18n validation.
#
# Validates staged .po files using msgfmt, msgcheck, and audit_po.py.
# Install as a git hook (chains into an existing pre-commit or replaces it):
#
#   chmod +x tests/tools/check-i18n.sh
#   # Option A: use it as your sole pre-commit hook
#   ln -sf ../../tests/tools/check-i18n.sh .git/hooks/pre-commit
#   # Option B: append to an existing pre-commit hook
#   echo 'exec tests/tools/check-i18n.sh "$@"' >> .git/hooks/pre-commit
#
# Or add to your pre-commit config:
#   - repo: local
#     hooks:
#       - id: check-i18n
#         name: i18n validation
#         entry: tests/tools/check-i18n.sh
#         language: script
#         files: '^locales/po/.*\.po$'
#
# Dependencies: gettext (msgfmt), msgcheck (pip install msgcheck),
#               python3, polib (pip install polib)

set -euo pipefail

REPO_ROOT="$(git rev-parse --show-toplevel)"
AUDIT_SCRIPT="$REPO_ROOT/tests/tools/audit_po.py"
ERRORS=0

# Collect staged .po files only, including renamed ones.
STAGED_PO=()
while IFS= read -r -d '' file; do
    case "$file" in
        *.po) STAGED_PO+=("$file") ;;
    esac
done < <(git diff --cached --name-only --diff-filter=ACMR -z)

if [ ${#STAGED_PO[@]} -eq 0 ]; then
    exit 0
fi

echo "i18n: checking ${#STAGED_PO[@]} staged .po file(s)..."

# Check the staged blobs, not the working tree, so the result describes what
# is about to be committed. Tools run inside the copy to report the same
# relative paths a reviewer sees.
STAGED_TREE="$(mktemp -d)"
trap 'rm -rf "$STAGED_TREE"' EXIT
printf '%s\0' "${STAGED_PO[@]}" | git checkout-index -z --stdin --prefix="$STAGED_TREE/"
GIT_DIR="$(git rev-parse --absolute-git-dir)"
export GIT_DIR
cd "$STAGED_TREE"

# 1. msgfmt syntax and format-string check
if ! command -v msgfmt &>/dev/null; then
    echo "ERROR: msgfmt not found. Install gettext (brew install gettext / apt install gettext)." >&2
    exit 1
fi
for po in "${STAGED_PO[@]}"; do
    if ! msgfmt -c -o /dev/null -- "$po" 2>&1; then
        echo "FAIL: msgfmt check failed for $po"
        ERRORS=1
    fi
done

# 2. msgcheck fuzzy/whitespace/punctuation lint (mirrors CI step)
if command -v msgcheck &>/dev/null; then
    if ! msgcheck --error-on-fuzzy --no-lines "${STAGED_PO[@]}"; then
        ERRORS=1
    fi
else
    echo "WARN: msgcheck not found; skipping fuzzy/whitespace lint." \
         "(pip install msgcheck to enable)" >&2
fi

# 3. Custom HTML and placeholder audit
if ! command -v python3 &>/dev/null; then
    echo "ERROR: python3 not found; cannot run audit_po.py." >&2
    exit 1
fi
if [ ! -f "$AUDIT_SCRIPT" ]; then
    echo "ERROR: audit script not found at $AUDIT_SCRIPT." >&2
    exit 1
fi
# Audit only entries the commit changes; GIT_DIR lets the audit read HEAD.
if ! python3 "$AUDIT_SCRIPT" --base HEAD "${STAGED_PO[@]}"; then
    ERRORS=1
fi

if [ "$ERRORS" -ne 0 ]; then
    echo ""
    echo "i18n validation failed. Fix the errors above before committing."
    exit 1
fi

echo "i18n: all checks passed."
