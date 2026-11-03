"""Offline syntax/configuration review, without any workload or Docker setup."""

import ast
from pathlib import Path
import subprocess

import yaml

root = Path(__file__).resolve().parents[2]
for path in (root / "build/diagnostics440").glob("*.py"):
    ast.parse(path.read_text(), filename=str(path))
workflow = yaml.load((root / ".github/workflows/benchmark.yml").read_text(), Loader=yaml.BaseLoader)
assert set(workflow["on"]) == {"workflow_dispatch"}
assert workflow["permissions"] == {"contents": "read"}
assert len(workflow["jobs"]) == 1
job = workflow["jobs"]["attribution-only"]
assert job["timeout-minutes"] == "40"
assert job["if"] == "github.ref == 'refs/heads/diagnostics-440-6250c'"
collection = []
for step in job["steps"]:
    if "run" in step:
        subprocess.run(["bash", "-n"], input=step["run"], text=True, check=True)
        assert "bench-compare" not in step["run"] and "bench-baseline" not in step["run"]
        if "collect.py collect" in step["run"]:
            assert step["timeout-minutes"] == "6"
            collection.append(step["run"].split()[-2:])
assert collection == [["baseline", "asset"], ["current", "asset"], ["baseline", "feed"], ["current", "feed"]]
assert job["steps"][-1]["if"] == "always()"
assert job["steps"][-2]["if"] == "always()"
assert job["steps"][-1]["uses"] == "actions/upload-artifact@cf430e030ddbb5b0abf93d22962f4752f3646cd9"
assert job["steps"][-1]["with"]["include-hidden-files"] == "true"
print("Python AST, YAML structure, dispatch-only scope, finite steps and Bash syntax accepted.")
