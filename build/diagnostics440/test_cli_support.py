"""Validate real Make recipes against CLI help; never execute the recipes."""

from pathlib import Path
import shlex
import subprocess
import unittest

from cli_support import validate_run


HELP = """Options:
  -e, --env stringArray    Environment
      --name string       Container name
      --pull string       Pull policy
      --rm                Remove container
"""


class CliSupportTest(unittest.TestCase):
    def test_unknown_option_is_rejected_instead_of_treated_as_an_image_policy(self):
        with self.assertRaisesRegex(ValueError, "--no-build"):
            validate_run(shlex.split("docker compose run --rm --no-build app php script.php"), HELP)

    def test_options_after_the_service_belong_to_php(self):
        validate_run(shlex.split("docker compose run --rm --pull never -e XDEBUG_MODE=off app php -l script.php"), HELP)

    def test_effective_make_recipes_against_supported_run_help(self):
        makefile = Path(__file__).with_name("commands.mk")
        for target in ["diagnostic-runtime", "diagnostic-profile"]:
            command = subprocess.check_output(["make", "-n", "-s", "-f", str(makefile), target,
                                               "PHASE=feed", "CONTAINER=cli-check"], text=True)
            validate_run(shlex.split(command), HELP)


if __name__ == "__main__":
    unittest.main()
