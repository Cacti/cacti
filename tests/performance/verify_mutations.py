"""Prove regression guards fail on historical defects in disposable copies."""
from pathlib import Path
import shutil
import subprocess
import tempfile
import sys

ROOT = Path(__file__).resolve().parents[2]
PHP = sys.argv[1] if len(sys.argv) > 1 else "php"
cases = [
    ("empty", "lib/poller.php", "if (!$rrd_field_names_loaded)", "if (!cacti_sizeof($rrd_field_names))"),
    ("quick", "lib/poller.php", "if (!array_key_exists($local_data_id, $unused_cache))", "if (true)"),
]
source = (ROOT / "lib/poller.php").read_text()
if "function poller_get_unused_data_source_names(" in source:
    cases[1] = ("quick", "lib/poller.php", "if (!array_key_exists($local_data_id, $cache))", "if (true)")
functions = (ROOT / "lib/functions.php").read_text()
if "function cacti_exec(" in functions:
    cases.append(("exec", "lib/functions.php", "@stream_select($read, $write, $except, $sec, $usec);", "@stream_select($read, $write, $except, $sec, $usec);\n\t\tusleep(50000);"))

for mode, filename, old, new in cases:
    with tempfile.TemporaryDirectory(prefix="cacti-perf-mutation-") as directory:
        root = Path(directory)
        shutil.copytree(ROOT / "tests/performance", root / "tests/performance")
        (root / "lib").mkdir()
        for name in ("poller.php", "functions.php"):
            shutil.copy2(ROOT / "lib" / name, root / "lib" / name)
        target = root / filename
        original = target.read_text()
        if old not in original:
            raise RuntimeError(f"Mutation anchor disappeared: {mode}")
        target.write_text(original.replace(old, new))
        result = subprocess.run([PHP, str(root / "tests/performance/poller.php"), mode], capture_output=True, text=True, timeout=30)
        expected = {"empty": "Empty template map was reloaded", "quick": "Metadata lookups grew", "exec": "Median wrapper overhead exceeds"}[mode]
        if result.returncode == 0 or expected not in result.stdout + result.stderr:
            raise RuntimeError(f"Guard did not reject {mode} mutation correctly: {result.stdout} {result.stderr}")
        print(f"{mode}: historical regression rejected")
