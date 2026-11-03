"""Diagnostic preparation/collection only; never runs PHPBench or changes a gate."""

import hashlib
import json
import os
from pathlib import Path
import re
import shutil
import signal
import subprocess
import sys
import time

from cachegrind import parse

TOOLS = Path(__file__).resolve().parent
ROOT = TOOLS.parents[1]
WORKSPACE = ROOT.parent
EVIDENCE = WORKSPACE / "diagnostic-evidence"
REFS = {"baseline": "d835f23ee83d494d151a01d13d683867b0830a3b",
        "current": "6250c776b8dc1cbb8657c01c594f71bb8a547eb1"}
ORDER = [("baseline", "asset"), ("current", "asset"), ("baseline", "feed"), ("current", "feed")]
BUDGET = 1024 ** 3
SECONDS = 345  # Leave time for stopping our container before the six-minute step limit.


def require(condition, message):
    if not condition:
        raise RuntimeError(message)


def digest(path):
    with path.open("rb") as stream:
        return hashlib.file_digest(stream, "sha256").hexdigest()


def write(path, data):
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(json.dumps(data, indent=2) + "\n")


def git(tree, *args):
    return subprocess.check_output(["git", "-C", str(tree), *args], text=True).strip()


def runtime_dir(ref):
    return WORKSPACE / ref / "runtime/runner-diagnostics"


def make(ref, target, *args):
    return ["make", "--no-print-directory", "-s", "-C", str(WORKSPACE / ref),
            "-f", str(TOOLS / "commands.mk"), target, *args]


def output(command):
    return subprocess.check_output(command, text=True, timeout=60)


def profiles():
    return [p for ref in REFS for p in (runtime_dir(ref) / "profiles").rglob("cachegrind.out.*")
            if p.is_file() and not p.name.endswith(".json")]


def size():
    return sum(p.stat().st_size for p in profiles())


def prepare():
    require(os.environ.get("GITHUB_RUN_ATTEMPT") == "1", "A rerun is not authorized; no collection")
    require(not EVIDENCE.exists(), "Evidence already exists; do not retry this experiment")
    EVIDENCE.mkdir()
    image = os.environ["DIAGNOSTIC_IMAGE"]
    require(re.fullmatch(r"yiipress-440-diagnostic:\d+-\d+", image), "Unexpected image name")
    write(EVIDENCE / "dispatch.json", {
        "diagnostic_head": git(ROOT, "rev-parse", "HEAD"), "source_refs": REFS,
        "run_url": f"https://github.com/{os.environ['GITHUB_REPOSITORY']}/actions/runs/{os.environ['GITHUB_RUN_ID']}",
        "job_name": os.environ["GITHUB_JOB"], "attempt": os.environ["GITHUB_RUN_ATTEMPT"],
        "image": image, "limits": {"job_minutes": 40, "collection_seconds": SECONDS, "raw_profile_bytes": BUDGET},
    })
    shutil.copytree(TOOLS, EVIDENCE / "tools", ignore=shutil.ignore_patterns("__pycache__"))
    shutil.copy2(ROOT / ".github/workflows/benchmark.yml", EVIDENCE / "workflow.yml")
    for ref, commit in REFS.items():
        tree = WORKSPACE / ref
        require(git(tree, "rev-parse", "HEAD") == commit, "Wrong source checkout")
        require(not git(tree, "status", "--porcelain"), "Source checkout must start clean")
        runtime = runtime_dir(ref)
        runtime.mkdir(parents=True)
        (runtime / "image.yml").write_text("services:\n  app:\n    build: !reset null\n    image: " + image + "\n")
        for name in ["runtime.php", "asset-child.php", "feed-child.php"]:
            shutil.copy2(TOOLS / name, runtime / name)
        for phase in ["asset", "feed"]:
            (runtime / f"profiles/{phase}").mkdir(parents=True)
            ini = runtime / f"{phase}-ini"
            ini.mkdir()
            (ini / "zz-diagnostic.ini").write_text(
                "xdebug.start_with_request=yes\n"
                f"xdebug.output_dir=/app/runtime/runner-diagnostics/profiles/{phase}\n"
                "xdebug.profiler_output_name=cachegrind.out.%p\n"
                "xdebug.use_compression=0\n"
            )


def identity():
    require(not (EVIDENCE / "identity.json").exists(), "Identity already recorded")
    records = {}
    image = os.environ["DIAGNOSTIC_IMAGE"]
    for ref, commit in REFS.items():
        tree = WORKSPACE / ref
        require(git(tree, "rev-parse", "HEAD") == commit, "Source ref changed")
        require(not git(tree, "status", "--porcelain"), "Tracked source or unexpected untracked file changed")
        config = json.loads(output(make(ref, "diagnostic-config")))
        require(config["services"]["app"]["image"] == image, "Different configured image")
        # Preserve effective structure, never env_file contents or expanded service environment values.
        for service in config["services"].values():
            service.pop("environment", None)
            service.pop("env_file", None)
        write(EVIDENCE / ref / "compose-sanitized.json", config)
        runtime = json.loads(output(make(ref, "diagnostic-runtime")))
        require("xdebug" in runtime["extensions"], "Xdebug absent")
        fixed = ["composer.lock", "phpbench.json", "Makefile", "benchmarks/AssetUrlRewriterBench.php",
                 "benchmarks/FeedBatchBench.php", "docker/Dockerfile", "docker/php.ini", "docker/php-dev.ini"]
        fixtures = {str(p.relative_to(tree)): digest(p) for p in sorted((tree / "benchmarks/data").rglob("*")) if p.is_file()}
        require(fixtures, "Generated fixtures absent")
        records[ref] = dict(ref=commit, image_id=output(make(ref, "diagnostic-image-id", "IMAGE=" + image)).strip(),
                            runtime=runtime, fixture_hashes=fixtures,
                            source_hashes={str(p.relative_to(tree)): digest(p) for p in sorted((tree / "src").rglob("*.php"))},
                            fixed_hashes={p: digest(tree / p) for p in fixed})
        write(EVIDENCE / ref / "identity.json", records[ref])
    base, current = records.values()
    for key in ["image_id", "runtime", "fixture_hashes", "fixed_hashes"]:
        require(base[key] == current[key], f"Identity mismatch: {key}")
    write(EVIDENCE / "identity.json", records)
    # Future collection resolves the inspected immutable image, rather than a movable tag.
    for ref in REFS:
        (runtime_dir(ref) / "image.yml").write_text(
            "services:\n  app:\n    build: !reset null\n    image: " + records[ref]["image_id"] + "\n")


def check_profile(path, phase, parent_profile):
    summary = parse(path)
    write(path.with_name(path.name + ".json"), summary)
    names = [r["function"] for r in summary["functions"]]
    if parent_profile:
        marker = "diagnosticAssetSteady" if phase == "asset" else "diagnosticFeedTarget"
        require(marker in names, "Named target wrapper absent from parent profile")
        counts = [("diagnosticAssetCold", 1), ("diagnosticAssetSteady", 1),
                  ("YiiPress\\Benchmarks\\AssetUrlRewriterBench->benchAlreadyFingerprintedPage", 101)] if phase == "asset" else [
                  ("diagnosticFeedTarget", 1), ("YiiPress\\Benchmarks\\FeedBatchBench->benchParallel", 1)]
        for marker, expected in counts:
            matches = [r for r in summary["functions"] if r["function"].replace("::", "->") == marker.replace("::", "->")]
            require(len(matches) == 1 and matches[0]["incoming_calls"] == expected,
                    f"Unexpected observed target call count: {marker}")
    else:
        for marker in ["WorkerCommand->runFeedJob", "FeedWriter", "FeedGenerator", "ContentProcessorPipeline"]:
            require(any(marker in n.replace("::", "->") for n in names), "Missing actual worker path: " + marker)
    return summary


def run_bounded(command, stdout, stderr, stop):
    started = time.monotonic()
    reason = None
    process = subprocess.Popen(command, stdout=stdout, stderr=stderr, start_new_session=True)
    try:
        while process.poll() is None:
            if size() > BUDGET:
                reason = "Cumulative raw-profile budget exceeded; partial files retained"
                break
            if time.monotonic() - started > SECONDS:
                reason = "Collection deadline exceeded"
                break
            time.sleep(0.1)
    finally:
        if process.poll() is None:
            try:
                # Stop only our named container. Leave time before Actions' outer six-minute cap.
                subprocess.run(stop, stdout=stderr, stderr=stderr, timeout=8, check=False)
            finally:
                try:
                    os.killpg(process.pid, signal.SIGKILL)
                except ProcessLookupError:
                    pass
                process.wait(timeout=3)
    return {"code": process.returncode, "stop_reason": reason, "raw_bytes": size()}


def collect(ref, phase):
    require((ref, phase) in ORDER, "Unknown collection")
    require((EVIDENCE / "identity.json").is_file(), "Runtime identity not accepted")
    require(not (EVIDENCE / "failure.json").exists(), "Prior failure forbids more collection")
    require(git(WORKSPACE / ref, "rev-parse", "HEAD") == REFS[ref], "Source ref changed before collection")
    require(not git(WORKSPACE / ref, "status", "--porcelain"), "Source changed before collection")
    for previous in ORDER[:ORDER.index((ref, phase))]:
        require((EVIDENCE / previous[0] / (previous[1] + "-complete.json")).exists(), "Earlier collection incomplete")
    destination = EVIDENCE / ref / phase
    require(not destination.exists(), "Collection already attempted; no retry")
    destination.mkdir(parents=True)
    require(not list((runtime_dir(ref) / f"profiles/{phase}").iterdir()), "Profiles directory is not empty")
    container = f"yiipress440-{os.environ['GITHUB_RUN_ID']}-{os.environ['GITHUB_RUN_ATTEMPT']}-{ref}-{phase}"
    command = make(ref, "diagnostic-profile", "PHASE=" + phase, "CONTAINER=" + container)
    write(destination / "command.json", command)
    with (destination / "stdout.json").open("w") as stdout, (destination / "stderr.log").open("w") as stderr:
        result = run_bounded(command, stdout, stderr, make(ref, "diagnostic-stop", "CONTAINER=" + container))
    write(destination / "exit.json", result)
    require(result["stop_reason"] is None and result["code"] == 0, result["stop_reason"] or "Driver failed")
    require(size() <= BUDGET, "Final cumulative profile budget exceeded")
    driver = json.loads((destination / "stdout.json").read_text())
    actual = driver["runtime"]
    common = json.loads((EVIDENCE / ref / "identity.json").read_text())["runtime"]
    for key in ["php", "binary_sha256", "pcre", "extensions", "xdebug_version"]:
        require(actual[key] == common[key], "Runtime changed during profile: " + key)
    for key in common["settings"]:
        if not key.startswith("xdebug."):
            require(actual["settings"][key] == common["settings"][key], "INI changed during profile: " + key)
    source_profiles = list((runtime_dir(ref) / f"profiles/{phase}").glob("cachegrind.out.*"))
    require(len(source_profiles) == 1 if phase == "asset" else 2 <= len(source_profiles) <= 5,
            "Missing parent/worker profiles or exceeded four workers")
    if phase == "feed":
        require(len(source_profiles) == driver["expected_workers"] + 1, "Some actual worker profiles are missing")
    parent_name = Path(driver["profile"]).name
    require(any(p.name == parent_name for p in source_profiles), "Parent profile missing")
    for path in source_profiles:
        check_profile(path, phase, path.name == parent_name)
    write(EVIDENCE / ref / (phase + "-complete.json"), {"profiles": [p.name for p in source_profiles]})


def finalize():
    EVIDENCE.mkdir(exist_ok=True)
    for ref in REFS:
        source = runtime_dir(ref)
        if source.exists():
            # Include all partial/oversized raw files; never silently drop evidence on failure.
            shutil.copytree(source, EVIDENCE / ref / "runtime", dirs_exist_ok=True)
    complete = all((EVIDENCE / ref / (phase + "-complete.json")).exists() for ref, phase in ORDER)
    manifest = {str(p.relative_to(EVIDENCE)): {"sha256": digest(p), "bytes": p.stat().st_size}
                for p in sorted(EVIDENCE.rglob("*")) if p.is_file() and p.name != "manifest.json"}
    write(EVIDENCE / "manifest.json", {"files": manifest, "raw_profile_bytes": size(),
        "status": "complete attribution collection" if complete else "inconclusive / incomplete collection",
        "limits": "Instrumented fresh runner only; historical cause and original gate success unproven. "
                  "Both remote failures remain open. No worker-inclusive summation into parent wall time."})


if __name__ == "__main__":
    def interrupted(signum, frame):
        raise RuntimeError(f"Collection interrupted by signal {signum}")

    signal.signal(signal.SIGTERM, interrupted)
    signal.signal(signal.SIGINT, interrupted)
    try:
        action = sys.argv[1]
        if action == "prepare":
            prepare()
        elif action == "identity":
            identity()
        elif action == "collect":
            collect(*sys.argv[2:])
        elif action == "finalize":
            finalize()
        else:
            raise RuntimeError("Unknown action")
    except Exception as error:
        write(EVIDENCE / "failure.json", {"status": "inconclusive", "reason": str(error)})
        raise
