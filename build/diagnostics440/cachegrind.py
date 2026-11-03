"""Read Xdebug Cachegrind self costs and call edges; never infer CPU causes."""

from collections import defaultdict
from pathlib import Path
import re


def parse(path: Path) -> dict:
    files, names = {}, {}
    functions = defaultdict(lambda: {"self": [], "outgoing": [], "incoming_calls": 0})
    edges = []
    events = []
    positions = 1
    filename = function = child_file = child = None
    calls = None
    total = None

    def symbol(value, table):
        match = re.fullmatch(r"\((\d+)\)(?: (.*))?", value)
        if not match:
            return value
        index, name = match.groups()
        if name is not None:
            table[index] = name
        if index not in table:
            raise ValueError(f"Undefined Cachegrind symbol {value}")
        return table[index]

    def add(target, values):
        if not target:
            target.extend([0] * len(values))
        if len(target) != len(values):
            raise ValueError("Cachegrind event width changed")
        for index, value in enumerate(values):
            target[index] += value

    with path.open() as stream:
        for raw in stream:
            line = raw.strip()
            if line.startswith("events: "):
                events = line[8:].split()
            elif line.startswith("positions: "):
                positions = len(line[11:].split())
            elif line.startswith("fl="):
                filename = symbol(line[3:], files)
            elif line.startswith("fn="):
                function = symbol(line[3:], names)
                child_file = child = None
                calls = None
            elif line.startswith(("cfl=", "cfi=")):
                child_file = symbol(line[4:], files)
            elif line.startswith("cfn="):
                child = symbol(line[4:], names)
            elif line.startswith("calls="):
                calls = int(line[6:].split()[0])
            elif line.startswith("summary: "):
                total = [int(n) for n in line[9:].split()]
            elif line and re.match(r"[\d*+\-]", line):
                if not events or filename is None or function is None:
                    raise ValueError("Cost without Cachegrind header/function")
                values = [int(n) for n in line.split()[positions:]]
                if len(values) != len(events):
                    raise ValueError("Unexpected Cachegrind cost width")
                key = (filename, function)
                if calls is None:
                    add(functions[key]["self"], values)
                else:
                    if child is None:
                        raise ValueError("Call cost without callee")
                    callee = (child_file or filename, child)
                    add(functions[key]["outgoing"], values)
                    functions[callee]["incoming_calls"] += calls
                    edges.append(dict(caller=list(key), callee=list(callee), calls=calls, inclusive=values))
                    calls = None
    if not events or not functions or total is None:
        raise ValueError("Incomplete or empty Cachegrind profile")
    rows = []
    for (file, name), costs in functions.items():
        own = costs["self"] or [0] * len(events)
        outgoing = costs["outgoing"] or [0] * len(events)
        rows.append(dict(file=file, function=name, self=own,
                         inclusive=[a + b for a, b in zip(own, outgoing)],
                         incoming_calls=costs["incoming_calls"]))
    return dict(events=events, summary=total, functions=rows, edges=edges,
                limits="Inclusive costs include callees; recursive/overlapping costs are not additive. "
                       "Incoming edge counts are observed calls, not sampled CPU instructions. "
                       "Keep raw event units; never sum worker inclusive cost into parent wall time.")
