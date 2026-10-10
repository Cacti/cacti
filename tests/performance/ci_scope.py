"""Select relevant CI work without skipping the required workflow itself."""
import fnmatch
import json
import os
import re
import subprocess

PATTERNS = (
    "poller*.php", "cmd.php", "script_server.php", "cactid*",
    "lib/*", "include/*", "resource/*", "composer.*", "cacti.sql",
    "tests/*", ".github/workflows/*", ".php*", "phpunit*", ".mise*",
    "mise.toml", ".tool-versions", "Docker*", "docker/*", "install/*",
)

def relevant(path):
    return any(fnmatch.fnmatchcase(path, pattern) for pattern in PATTERNS)

def select(event, base, head):
    full = event in {"schedule", "release"} or (
        event == "workflow_dispatch" and os.getenv("FULL", "true") == "true"
    )
    run = event in {"schedule", "release", "workflow_dispatch"}
    if not run:
        if not re.fullmatch(r"[0-9a-f]{40}", base or "") or set(base) == {"0"}:
            run = True  # New branch or unavailable base: fail open to testing.
        else:
            result = subprocess.run(
                ["git", "diff", "--name-only", "--no-renames", "-z", base, head],
                capture_output=True, check=False,
            )
            run = result.returncode != 0 or any(
                relevant(p.decode("utf-8", errors="replace"))
                for p in result.stdout.split(b"\0") if p
            )
    refs = ["develop", "1.2.x"] if event == "schedule" else [head]
    matrix = {"include": [
        {"ref": ref, "php": php}
        for ref in refs
        for php in (["8.2", "8.3", "8.4"] if (ref == "1.2.x" or os.getenv("TRACK") == "1.2.x") else ["8.3", "8.4"])
    ]}
    return {"run": str(run).lower(), "full": str(full).lower(), "refs": json.dumps(refs), "matrix": json.dumps(matrix)}

if __name__ == "__main__":
    outputs = select(os.environ["EVENT"], os.getenv("BASE", ""), os.environ["HEAD"])
    with open(os.environ["GITHUB_OUTPUT"], "a", encoding="utf-8") as out:
        for key, value in outputs.items():
            out.write(f"{key}={value}\n")
    print(json.dumps(outputs))
