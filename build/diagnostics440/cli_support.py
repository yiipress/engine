"""Read-only validation against the installed Compose CLI and effective configuration."""

import argparse
import json
from pathlib import Path
import re
import shlex
import subprocess


def validate_run(command, help_text):
    supported = set(re.findall(r"(?<!\S)(--[a-z][a-z-]*|-[A-Za-z])(?=[ ,=]|$)", help_text, re.M))
    # Help prints short flags with a comma before their long spelling.
    supported.update(re.findall(r"(?<=, )(--[a-z][a-z-]*)(?=[ =]|$)", help_text, re.M))
    start = command.index("run") + 1
    stop = command.index("app", start)
    unknown = [value.split("=", 1)[0] for value in command[start:stop]
               if value.startswith("-") and value.split("=", 1)[0] not in supported]
    if unknown:
        raise ValueError("Unsupported installed Compose run options: " + ", ".join(unknown))


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("tree", type=Path)
    parser.add_argument("--override", type=Path)
    options = parser.parse_args()
    make = ["make", "--no-print-directory", "-s", "-C", str(options.tree.resolve()),
            "-f", str(Path(__file__).with_name("commands.mk").resolve())]
    if options.override:
        make.append("COMPOSE=docker compose -f docker/compose.yml -f docker/dev/compose.yml -f "
                    + str(options.override.resolve()))
    help_text = subprocess.check_output([*make, "diagnostic-run-help"], text=True)
    for target in ["diagnostic-runtime", "diagnostic-profile"]:
        command = shlex.split(subprocess.check_output(
            [*make, "-n", target, "PHASE=feed", "CONTAINER=task440-cli-validation"], text=True).strip())
        validate_run(command, help_text)
        print("Installed CLI accepts: " + shlex.join(command))
    config = json.loads(subprocess.check_output([*make, "diagnostic-config"], text=True))
    app = config["services"]["app"]
    if app.get("build") is not None or not app.get("image"):
        raise ValueError("Effective app must have an image and no build definition")
    print("Effective app image: " + app["image"] + "; no implicit build definition.")


if __name__ == "__main__":
    main()
