#!/usr/bin/env python3
"""Minimal least-squares engine used by VERDELIA chat analytics."""

import json
import math
import sys


MAX_MONTHLY_UNITS = 1_000_000_000


def valid_series(value):
    return (
        isinstance(value, list)
        and 3 <= len(value) <= 12
        and all(type(item) is int and 0 <= item <= MAX_MONTHLY_UNITS for item in value)
    )


def linear(values):
    n = len(values)
    xs = list(range(n))
    mean_x = sum(xs) / n
    mean_y = sum(values) / n
    denominator = sum((x - mean_x) ** 2 for x in xs)
    if denominator <= 0 or not math.isfinite(denominator):
        return {"available": False, "slope": None, "forecast": []}

    slope = sum((x - mean_x) * (y - mean_y) for x, y in zip(xs, values)) / denominator
    intercept = mean_y - slope * mean_x
    forecast = intercept + slope * n
    if not math.isfinite(slope) or not math.isfinite(intercept) or not math.isfinite(forecast):
        return {"available": False, "slope": None, "forecast": []}
    return {"available": True, "slope": slope, "forecast": [forecast]}


def main():
    try:
        if len(sys.argv) != 2:
            raise ValueError("expected one JSON series argument")
        values = json.loads(sys.argv[1])
        if not valid_series(values):
            raise ValueError("invalid monthly series")
        result = linear(values)
        sys.stdout.write(json.dumps(result, allow_nan=False, separators=(",", ":")))
        return 0
    except (ValueError, TypeError, OverflowError, json.JSONDecodeError):
        return 2


if __name__ == "__main__":
    raise SystemExit(main())
