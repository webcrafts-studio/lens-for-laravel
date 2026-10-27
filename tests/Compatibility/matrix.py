#!/usr/bin/env python3
"""Install isolated consumers and run executable integration probes against their dependencies."""
import argparse
import concurrent.futures
import json
import hashlib
import os
from pathlib import Path
import shutil
import subprocess
import time

PACKAGE = Path(__file__).resolve().parents[2]


def scenarios():
    cases = []
    for php in ("82", "83", "84", "85"):
        for laravel in (10, 11, 12, 13):
            if php == "82" and laravel == 13:
                continue
            cases.append(dict(name=f"core-php{php}-l{laravel}", php=php, laravel=f"^{laravel}.0", packages={}))
    for version in ("0.3.2", "0.4.0", "0.4.4", "0.4.5", "0.5.0", "0.5.1", "0.6.0", "0.6.1", "0.6.8", "0.7.0", "0.7.2", "0.8.0", "0.8.1", "0.9.0", "0.9.1", "0.10.0", "0.10.3", "0.11.0", "0.11.3", "1.0.0", "1.3.0"):
        cases.append(dict(name=f"ai-{version}", php="83", laravel="^12.62", packages={"laravel/ai": version, "laravel/mcp": "^1.0"}))
    for php in ("83", "84", "85"):
        cases.append(dict(name=f"ai-l13-php{php}", php=php, laravel="^13.15", packages={"laravel/ai": "^1.0", "laravel/mcp": "^1.0"}))
    for version in ("0.5.3", "0.5.9", "0.6.0", "0.6.3", "0.6.7", "0.7.0", "0.7.2", "0.8.0", "0.8.2", "0.9.0", "0.9.7", "1.0.0", "1.1.2"):
        cases.append(dict(name=f"mcp-{version}", php="83", laravel="^12.62", packages={"laravel/mcp": version}))
    cases.append(dict(name="mcp-l10-php82", php="82", laravel="^10.49", packages={"laravel/mcp": ">=0.5.3 <0.10 || ^1.0"}))
    profiles = {
        "reactive": {"livewire/livewire": "*", "inertiajs/inertia-laravel": "*", "laravel/sanctum": "*", "spatie/laravel-permission": "*"},
        "admin": {"filament/filament": "*", "spatie/laravel-permission": "*", "laravel/sanctum": "*"},
        "debug": {"laravel/boost": "*", "laravel/telescope": "*", "barryvdh/laravel-debugbar": "*", "laravel/horizon": "*"},
    }
    for laravel in (10, 11, 12, 13):
        for profile, packages in profiles.items():
            packages = dict(packages, **{"laravel/mcp": ">=0.5.3 <0.10 || ^1.0"})
            if laravel >= 12:
                packages["laravel/ai"] = ">=0.3.2 <0.12 || ^1.0"
            cases.append(dict(name=f"ecosystem-{profile}-l{laravel}", php="83", laravel=f"^{laravel}.0", packages=packages))
    for laravel, ai, mcp in ((12, "0.3.2", "0.6.7"), (12, "0.11.3", "0.9.7"), (13, "^1.0", "^1.0")):
        packages = {key: value for group in profiles.values() for key, value in group.items()}
        packages.update({"laravel/ai": ai, "laravel/mcp": mcp})
        cases.append(dict(name=f"ecosystem-combined-l{laravel}-ai{ai}", php="83", laravel=f"^{laravel}.0", packages=packages))
    for laravel, php in ((10, "82"), (11, "82"), (12, "82"), (13, "83")):
        cases.append(dict(name=f"lowest-core-l{laravel}", php=php, laravel=f"^{laravel}.0", packages={}, lowest=True))
    for laravel, ai in ((12, "0.3.2"), (12, "1.0.0"), (13, "1.0.0")):
        cases.append(dict(name=f"lowest-ai{ai}-l{laravel}", php="83", laravel=f"^{laravel}.0", packages={"laravel/ai": ai}, lowest=True, ai_unavailable=ai == "0.3.2"))
    cases.append(dict(name="lowest-ai0.3.2-supported-l12", php="83", laravel="^12.0", packages={"laravel/ai": "0.3.2", "prism-php/prism": "^0.99.1"}, lowest=True))
    return cases


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--root", required=True, type=Path)
    parser.add_argument("--php-dir", type=Path, default=Path.home() / "Library/Application Support/Herd/bin")
    parser.add_argument("--only", default="")
    parser.add_argument("--jobs", type=int, default=3)
    parser.add_argument("--list", action="store_true")
    parser.add_argument("--retry-failed", action="store_true")
    parser.add_argument("--allow-legacy-advisories", action="store_true", help="Only isolated Laravel 10/11 fixtures; retain advisory evidence in reports")
    args = parser.parse_args()
    selected = [case for case in scenarios() if args.only in case["name"]]
    report = args.root / ("report-" + (args.only or "all") + ".json")
    retained = []
    if args.retry_failed and report.exists():
        previous = json.loads(report.read_text())
        retained = [case for case in previous if case["status"] == "passed"]
        completed = {case["name"] for case in retained}
        selected = [case for case in selected if case["name"] not in completed]
    if args.list:
        print(json.dumps(selected, indent=2))
        return
    args.root.mkdir(parents=True, exist_ok=True)
    if args.root.resolve() == PACKAGE or PACKAGE in args.root.resolve().parents:
        raise RuntimeError("Consumer applications must be outside the package repository")
    composer = shutil.which("composer")
    if not composer:
        raise RuntimeError("Composer is required")

    def run(case):
        start = time.monotonic()
        root = args.root / case["name"]
        root.mkdir(exist_ok=True)
        marker = root / ".lens-compatibility-consumer"
        identity = hashlib.sha256(str(root.resolve()).encode()).hexdigest()
        if (root / "composer.json").exists() and (not marker.exists() or marker.read_text().strip() != identity):
            raise RuntimeError("Refusing to overwrite an unmarked application: " + str(root))
        marker.write_text(identity + "\n")
        php = str(args.php_dir / ("php" + case["php"]))
        version = subprocess.check_output([php, "-r", "echo PHP_VERSION;"], text=True)
        manifest = {
            "name": "lens/compatibility-consumer", "type": "project",
            "require": {"php": "^8.2", "laravel/framework": case["laravel"], "webcrafts-studio/lens-for-laravel": "dev-main", **case["packages"]},
            "repositories": [{"type": "path", "url": str(PACKAGE), "options": {"symlink": True, "versions": {"webcrafts-studio/lens-for-laravel": "dev-main"}}}],
            "minimum-stability": "stable", "prefer-stable": True,
            "autoload": {"psr-4": {"App\\": "app/"}},
            "config": {"allow-plugins": False, "platform": {"php": version}},
        }
        legacy = case["laravel"].startswith(("^10.", "^11."))
        historical = legacy or case.get("lowest", False)
        if historical and args.allow_legacy_advisories:
            manifest["config"]["audit"] = {"block-insecure": False}
        (root / "composer.json").write_text(json.dumps(manifest, indent=2) + "\n")
        env = dict(os.environ, COMPOSER_CACHE_DIR=str(args.root / "composer-cache"))
        if case.get("ai_unavailable"):
            env["LENS_COMPAT_EXPECT_AI_UNAVAILABLE"] = "1"
        commands = [([php, composer, "update", "--no-interaction", "--no-scripts", "--prefer-dist"], "install"),
                    ([php, composer, "check-platform-reqs"], "platform"),
                    ([php, str(PACKAGE / "tests/Compatibility/core.php")], "core")]
        if case.get("lowest"):
            commands[0][0].extend(["--prefer-lowest", "--prefer-stable"])
        if "laravel/ai" in case["packages"]:
            commands.append(([php, str(PACKAGE / "tests/Compatibility/ai-http.php")], "ai-http"))
        if case["name"].startswith("ecosystem"):
            commands.append(([php, str(PACKAGE / "tests/Compatibility/ecosystem.php")], "ecosystem"))
        evidence = {}
        for command, phase in commands:
            result = subprocess.run(command, cwd=root, env=env, text=True, stdout=subprocess.PIPE, stderr=subprocess.STDOUT, timeout=600)
            (root / (phase + ".log")).write_text(result.stdout)
            if result.returncode != 0:
                return dict(case, status="failed", phase=phase, output=result.stdout[-5000:], seconds=round(time.monotonic() - start, 2))
            if phase in ("core", "ai-http", "ecosystem"):
                try:
                    evidence[phase] = json.loads(result.stdout.strip().splitlines()[-1])
                except (ValueError, IndexError):
                    return dict(case, status="failed", phase=phase, output=result.stdout[-5000:])
        installed = json.loads((root / "composer.lock").read_text())["packages"]
        versions = {item["name"]: item["version"] for item in installed}
        audit = subprocess.run([php, composer, "audit", "--locked", "--format=json", "--no-interaction"], cwd=root, env=env, text=True, stdout=subprocess.PIPE, stderr=subprocess.STDOUT, timeout=120)
        (root / "audit.log").write_text(audit.stdout)
        return dict(case, status="passed", evidence=evidence, versions=versions, advisory_exit=audit.returncode,
                    legacy_advisories_allowed=historical and args.allow_legacy_advisories, seconds=round(time.monotonic() - start, 2))

    results = retained
    with concurrent.futures.ThreadPoolExecutor(max_workers=args.jobs) as executor:
        futures = {executor.submit(run, case): case for case in selected}
        for future in concurrent.futures.as_completed(futures):
            case = futures[future]
            try:
                result = future.result()
            except Exception as error:
                result = dict(case, status="failed", output=str(error))
            results.append(result)
            print(result["status"].upper(), case["name"], result.get("phase", ""), flush=True)
            if result["status"] != "passed":
                print(result.get("output", ""), flush=True)
            report.write_text(json.dumps(results, indent=2))
    print(json.dumps({"passed": sum(item["status"] == "passed" for item in results), "total": len(results)}))
    raise SystemExit(any(item["status"] != "passed" for item in results))


if __name__ == "__main__":
    main()
