#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/../.." &> /dev/null && pwd)"
STAMP="$(date +%Y%m%d-%H%M%S)"
OUT_BASE="${ROOT_DIR}/security/proof-freeze/${STAMP}"
REPO="${1:-Cacti/cacti}"
BRANCHES="${2:-1.2.x develop}"

mkdir -p "$OUT_BASE"

"${ROOT_DIR}/tests/security/build_private_advisory_matrix.sh" "$REPO" "$BRANCHES" "$OUT_BASE"
"${ROOT_DIR}/tests/security/build_sink_inventory.sh" > "${OUT_BASE}/sink_inventory.current.tsv"
"${ROOT_DIR}/tests/security/build_architectural_helper_report.sh" --summary > "${OUT_BASE}/architectural_helper.summary.tsv"
"${ROOT_DIR}/tests/security/build_architectural_helper_report.sh" --hotspots > "${OUT_BASE}/architectural_helper.hotspots.tsv"

cat > "${OUT_BASE}/README.txt" <<EOF
Private advisory evidence freeze
timestamp=${STAMP}
repo=${REPO}
branches=${BRANCHES}

Artifacts:
- private_advisory_source.json
- private_advisory_keys.txt
- private_advisory_proof_matrix.tsv
- sink_inventory.current.tsv
- architectural_helper.summary.tsv
- architectural_helper.hotspots.tsv
EOF

echo "WROTE: ${OUT_BASE}"
