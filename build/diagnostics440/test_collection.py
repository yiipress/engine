"""Synthetic/offline collection checks. No Docker, PHP workload or profiling."""

import io
from pathlib import Path
import tempfile
import unittest
from unittest.mock import Mock, patch

import collect
from cachegrind import parse


SAMPLE = """version: 1
positions: line
events: Time_(10ns) Memory_(bytes)
fl=(1) /app/driver.php
fn=(1) parent
1 10 20
cfl=(2) /app/child.php
cfn=(2) child
calls=3 0 0
2 50 60
fl=(2)
fn=(2)
3 50 60
summary: 60 80
"""


class CollectionTest(unittest.TestCase):
    def test_self_and_call_costs_are_distinct_with_compressed_symbols(self):
        with tempfile.TemporaryDirectory() as directory:
            path = Path(directory) / "profile"
            path.write_text(SAMPLE)
            result = parse(path)
        parent = next(row for row in result["functions"] if row["function"] == "parent")
        child = next(row for row in result["functions"] if row["function"] == "child")
        self.assertEqual(parent["self"], [10, 20])
        self.assertEqual(parent["inclusive"], [60, 80])
        self.assertEqual(child["incoming_calls"], 3)
        self.assertEqual(result["edges"][0]["inclusive"], [50, 60])

    def test_incomplete_or_undefined_profile_is_inconclusive(self):
        with tempfile.TemporaryDirectory() as directory:
            path = Path(directory) / "profile"
            for sample in ["events: Time_(10ns)\n", SAMPLE.replace("fn=(2)\n", "fn=(999)\n")]:
                path.write_text(sample)
                with self.assertRaises(ValueError):
                    parse(path)

    def test_worker_profile_must_contain_actual_feed_pipeline(self):
        with tempfile.TemporaryDirectory() as directory:
            path = Path(directory) / "profile"
            path.write_text(SAMPLE)
            with self.assertRaisesRegex(RuntimeError, "Missing actual worker path"):
                collect.check_profile(path, "feed", False)

    def test_no_retry_after_a_recorded_failure(self):
        with tempfile.TemporaryDirectory() as directory, patch.object(collect, "EVIDENCE", Path(directory)):
            (Path(directory) / "identity.json").write_text("{}")
            (Path(directory) / "failure.json").write_text("{}")
            with patch.object(collect.subprocess, "Popen") as process:
                with self.assertRaisesRegex(RuntimeError, "Prior failure"):
                    collect.collect("baseline", "asset")
                process.assert_not_called()

    def bounded_stop(self, sizes, times):
        process = Mock(pid=42, returncode=-9)
        process.poll.return_value = None
        with patch.object(collect.subprocess, "Popen", return_value=process), \
                patch.object(collect.subprocess, "run") as stop, \
                patch.object(collect.os, "killpg") as kill, \
                patch.object(collect, "size", side_effect=sizes), \
                patch.object(collect.time, "monotonic", side_effect=times):
            result = collect.run_bounded(["dummy"], io.StringIO(), io.StringIO(), ["stop-owned-container"])
        stop.assert_called_once()
        kill.assert_called_once_with(42, collect.signal.SIGKILL)
        process.wait.assert_called_once()
        return result

    def test_cumulative_budget_stops_process_and_retains_failure(self):
        result = self.bounded_stop([collect.BUDGET + 1, collect.BUDGET + 1], [0])
        self.assertIn("budget exceeded", result["stop_reason"])

    def test_deadline_stops_owned_container(self):
        result = self.bounded_stop([0, 0], [0, collect.SECONDS + 1])
        self.assertEqual(result["stop_reason"], "Collection deadline exceeded")


if __name__ == "__main__":
    unittest.main()
