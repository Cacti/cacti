"""Prove regression guards fail on historical defects in disposable copies."""
from pathlib import Path
import shutil
import subprocess
import tempfile
import sys
import os

HARNESS_ROOT = Path(__file__).resolve().parents[2]
ROOT = Path(os.environ.get("CACTI_POLLER_SOURCE_ROOT", HARNESS_ROOT))
PHP = sys.argv[1] if len(sys.argv) > 1 else "php"
cases = [
    ("recovery", "lib/poller.php", "$template_fields !== false", "true"),
    ("empty", "lib/poller.php", "if (!$rrd_field_names_loaded)", "if (!cacti_sizeof($rrd_field_names))"),
    ("quick", "lib/poller.php", "if (!array_key_exists($local_data_id, $unused_cache))", "if (true)"),
]
source = (ROOT / "lib/poller.php").read_text()
if "function poller_get_unused_data_source_names(" in source:
    cases[2] = ("quick", "lib/poller.php", "if (!array_key_exists($local_data_id, $cache))", "if (true)")
if "function process_poller_output_batch(" in source:
    old = "if ($template_fields === false) {\n\t\t\treturn 0;"
    cases[0] = ("recovery", "lib/poller.php", old, old.replace("return 0;", "$rrd_field_names_loaded = true; return 0;"))
    if "if ($unused_sources === null)" in source:
        cases[2] = ("quick", "lib/poller.php", "if ($unused_sources === null)", "if (true)")
    else:
        cases[2] = ("quick", "lib/boost.php", "if ($cache !== null && $generation === $version)", "if (false)")
functions = (ROOT / "lib/functions.php").read_text()
if "function cacti_exec(" in functions:
    cases.append(("exec", "lib/functions.php", "@stream_select($read, $write, $except, $sec, $usec);", "@stream_select($read, $write, $except, $sec, $usec);\n\t\tusleep(50000);"))

for mode, filename, old, new in cases:
    with tempfile.TemporaryDirectory(prefix="cacti-perf-mutation-") as directory:
        root = Path(directory)
        shutil.copytree(HARNESS_ROOT / "tests/performance", root / "tests/performance")
        (root / "lib").mkdir()
        for name in ("poller.php", "functions.php", "boost.php"):
            shutil.copy2(ROOT / "lib" / name, root / "lib" / name)
        target = root / filename
        original = target.read_text()
        if old not in original:
            raise RuntimeError(f"Mutation anchor disappeared: {mode}")
        target.write_text(original.replace(old, new))
        result = subprocess.run([PHP, str(root / "tests/performance/poller.php"), mode], capture_output=True, text=True, timeout=30, env={**os.environ, "CACTI_POLLER_SOURCE_ROOT": str(root)})
        expected = {"recovery": "Failed mapping read was cached", "empty": "Empty template map was reloaded", "quick": "Metadata lookups grew", "exec": "Median wrapper overhead exceeds"}[mode]
        if result.returncode == 0 or expected not in result.stdout + result.stderr:
            raise RuntimeError(f"Guard did not reject {mode} mutation correctly: {result.stdout} {result.stderr}")
        print(f"{mode}: historical regression rejected")
