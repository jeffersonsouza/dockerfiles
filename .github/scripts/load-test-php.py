#!/usr/bin/env python3
"""Exercise a disposable PHP/Nginx container with ApacheBench and cgroup limits."""

import argparse
import json
import os
from pathlib import Path
import re
import subprocess
import time
import urllib.request


def run(*args, **kwargs):
    return subprocess.run(args, check=True, text=True, capture_output=True, **kwargs).stdout.strip()


def metric(output, name, default=None):
    match = re.search(r"^" + re.escape(name) + r":\s+([\d.]+)", output, re.MULTILINE)
    if match:
        return float(match.group(1))
    if default is not None:
        return default
    raise ValueError("Missing ApacheBench metric: " + name)


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("image")
    parser.add_argument("series", choices=["8.2", "8.3", "8.4", "8.5"])
    parser.add_argument("--memory", type=int, choices=[256, 512], default=256, help="MiB")
    parser.add_argument("--children", type=int, choices=[1, 2], default=1)
    parser.add_argument("--seconds", type=int, default=10, help="duration of each of five stages")
    parser.add_argument("--output", type=Path, required=True)
    args = parser.parse_args()
    if args.seconds < 1:
        parser.error("--seconds must be positive")
    args.output.mkdir(parents=True, exist_ok=False)

    container = run("docker", "run", "--detach", "--memory", f"{args.memory}m", "--memory-swap", f"{args.memory}m",
                    "--cpus", "1", "--env", f"PHP_FPM_MAX_CHILDREN={args.children}",
                    "--label", "codex.task=php-load-test", "--publish", "127.0.0.1::80", args.image)
    results = []
    try:
        info = json.loads(run("docker", "inspect", container))[0]
        port = info["NetworkSettings"]["Ports"]["80/tcp"][0]["HostPort"]
        base = "http://127.0.0.1:" + port

        def fetch(path):
            request = urllib.request.Request(base + path, headers={"Accept": "application/json"})
            with urllib.request.urlopen(request, timeout=10) as response:
                assert response.status == 200
                return json.load(response), response.headers

        for attempt in range(40):
            try:
                initial, headers = fetch("/")
                break
            except (OSError, ValueError):
                if attempt == 39:
                    raise
                time.sleep(0.25)
        assert initial["status"] == "ok" and initial["php_version"].startswith(args.series + ".")
        assert all(initial["checks"].values()) and headers["Cache-Control"] == "no-store"

        fixture = '''<?php
$public = ($_GET['mode'] ?? '') === 'public';
header('Content-Type: application/json');
header($public ? 'Cache-Control: public, max-age=60' : 'Cache-Control: no-store');
if (!$public) {
    $buffer = str_repeat('x', 96 * 1024 * 1024);
    hash_pbkdf2('sha256', 'benchmark', 'fixed-test-salt', 10000, 32, true);
    usleep(20000);
}
echo json_encode(['status' => 'ok', 'php_version' => PHP_VERSION, 'generated_at' => microtime(true)]);
'''
        run("docker", "exec", "-i", container, "sh", "-c", "cat > /var/www/html/load-test.php", input=fixture)
        for _ in range(20):
            fetch("/")
        # A fixed public response is primed only after the uncached stages.
        stages = [("index-c1", "/", 1), ("index-c8", "/", 8), ("index-c32", "/", 32),
                  ("memory96-c32", "/load-test.php", 32), ("cache-c32", "/load-test.php?mode=public", 32)]
        for name, path, concurrency in stages:
            if name == "cache-c32":
                fetch(path)
                _, cached_headers = fetch(path)
                assert cached_headers["X-FastCGI-Cache"] == "HIT"
            command = ["ab", "-q", "-r", "-k", "-l", "-s", "10", "-t", str(args.seconds), "-n", "1000000",
                       "-c", str(concurrency), "-H", "Accept: application/json", base + path]
            env = dict(os.environ, LC_ALL="C")
            result = subprocess.run(command, text=True, capture_output=True, timeout=args.seconds + 30, env=env)
            (args.output / (name + ".txt")).write_text(result.stdout + "\n" + result.stderr)
            result.check_returncode()
            output = result.stdout
            row = {"stage": name, "concurrency": concurrency, "requests": int(metric(output, "Complete requests")),
                   "failed": int(metric(output, "Failed requests")), "non_2xx": int(metric(output, "Non-2xx responses", 0)),
                   "write_errors": int(metric(output, "Write errors", 0)), "rps": metric(output, "Requests per second"),
                   "keepalive_requests": int(metric(output, "Keep-Alive requests", 0)),
                   "seconds": metric(output, "Time taken for tests")}
            for percentile in (95, 99):
                match = re.search(r"^\s*" + str(percentile) + r"%\s+(\d+)", output, re.MULTILINE)
                if not match:
                    raise ValueError("Missing latency percentile")
                row[f"p{percentile}_ms"] = int(match.group(1))
            results.append(row)
            print(json.dumps({"series": args.series, "memory_mib": args.memory, **row}), flush=True)
            assert row["requests"] > 0 and row["failed"] == row["non_2xx"] == row["write_errors"] == 0, row
            if name != "cache-c32":
                _, live_headers = fetch(path)
                assert live_headers["Cache-Control"] == "no-store" and live_headers.get("X-FastCGI-Cache") != "HIT"

        final, _ = fetch("/")
        assert final["status"] == "ok" and initial["generated_at"] != final["generated_at"]
        stats = run("docker", "exec", container, "sh", "-c",
                    "cat /sys/fs/cgroup/memory.peak; cat /sys/fs/cgroup/memory.events; cat /sys/fs/cgroup/cpu.stat")
        peak, *events = stats.splitlines()
        counters = {name: int(value) for name, value in (line.split() for line in events)}
        info = json.loads(run("docker", "inspect", container))[0]
        logs = run("docker", "logs", container)
        (args.output / "container.log").write_text(logs)
        assert not info["State"]["OOMKilled"] and info["State"]["Running"] and info["RestartCount"] == 0
        assert info["State"]["Health"]["Status"] != "unhealthy"
        assert counters["oom_kill"] == counters["oom"] == 0, counters
        assert int(peak) < args.memory * 1024 * 1024
        assert not re.search(r"SIGSEGV|SIGBUS|segmentation fault|Allowed memory size|failed to ptrace", logs, re.IGNORECASE)
        assert not re.search(r"exited: (?:php-fpm|nginx)", logs)
        assert not re.search(r'" 5\d\d ', logs)
        summary = {"series": args.series, "php_version": initial["php_version"], "image": args.image,
                   "image_id": info["Image"], "memory_mib": args.memory, "workers": args.children, "cpus": 1,
                   "architecture": initial["environment"]["architecture"], "memory_peak_bytes": int(peak),
                   "client_protocol": "HTTP/1.0 (ApacheBench)", "health": info["State"]["Health"]["Status"],
                   "cgroup_events": counters, "stages": results}
        run("docker", "stop", "--time", "140", container)
        assert json.loads(run("docker", "inspect", container))[0]["State"]["ExitCode"] == 0
        (args.output / "summary.json").write_text(json.dumps(summary, indent=2) + "\n")
        print(f"PASS PHP {args.series}, {args.memory} MiB, peak {int(peak) / 1024 / 1024:.1f} MiB", flush=True)
    finally:
        run("docker", "rm", "--force", container)


if __name__ == "__main__":
    main()
