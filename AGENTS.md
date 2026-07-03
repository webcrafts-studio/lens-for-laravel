# Lens for Laravel — Contributor Guide

## Project Scope

Lens for Laravel is a local-first accessibility auditing package for Laravel applications. It renders application pages in Chromium, runs axe-core, maps violations back to Blade/React/Vue source files, and exposes the results through a dashboard and the `lens:audit` Artisan command.

The current development line is v3.0.0. New compatibility, WCAG selection, reliability, localization, and documentation work in this branch must be described as v3 functionality. Keep v2.0/v2.1 upgrade notes as historical documentation.

The package supports PHP 8.2+ and Laravel 10–13 for its core, non-AI features.

AI Fix is an optional integration with a narrower compatibility range:

- PHP 8.3+
- Laravel 12+
- optional `laravel/ai` Composer package

Do not add `laravel/ai` back to production `require`. Core installation on PHP 8.2 and Laravel 10/11 must remain possible. Code that touches the AI SDK must be guarded through `AiFixAvailability`.

## Main Components

- `src/Services/AxeScanner.php` — browser-based axe-core scans, including interactive states
- `src/Services/SiteCrawler.php` — sitemap and internal-link discovery
- `src/Services/FileLocator.php` — heuristic Blade/React/Vue source mapping
- `src/Services/InteractionScriptParser.php` — state script validation and parsing
- `src/Services/BaselineManager.php` — stable CI baseline fingerprints
- `src/Services/ScanComparator.php` — URL- and state-aware history comparison
- `src/Services/AiFixAvailability.php` — runtime and optional-SDK capability checks
- `src/Services/AiFixer.php` — optional AI-generated fix suggestions
- `src/Support/Wcag.php` — supported WCAG versions, cumulative axe-core tags, and result-level classification
- `src/Console/Commands/LensAuditCommand.php` — CLI audit workflow
- `routes/web.php` — dashboard JSON endpoints
- `resources/views/dashboard.blade.php` — dashboard interface
- `resources/views/state-recorder.blade.php` — visual interaction recorder
- `resources/views/report.blade.php` — PDF report

## Compatibility Rules

- Keep core code syntactically compatible with PHP 8.2.
- Keep core dependencies compatible with Laravel 10, 11, 12, and 13.
- Treat AI Fix as unavailable when the runtime or optional SDK is unsupported.
- Never let a missing AI SDK break scanning, crawling, history, PDF reports, previews, interactive states, or the CLI.
- Keep WCAG 2.0 as the default unless a breaking release explicitly changes it. WCAG 2.1 and 2.2 scans must include the earlier cumulative rule tags.
- Treat the WCAG version and conformance level as separate controls: `--wcag` selects 2.0/2.1/2.2, while `--a`, `--aa`, and `--all` filter result levels.
- When compatibility changes, update `composer.json`, `README.md`, `CONTEXT.md`, tests, and the separate `lens-for-laravel-website` documentation together.

## Development Workflow

Run the full test suite:

```bash
composer test
```

Format PHP changes:

```bash
vendor/bin/pint --dirty
```

Validate Composer metadata:

```bash
composer validate --no-check-publish
```

The optional AI SDK is not part of the default development dependency graph. Install it temporarily when testing real provider integration on PHP 8.3+ and Laravel 12+:

```bash
composer require laravel/ai:^0.3.2 --dev
```

Do not commit that temporary dependency as a mandatory production requirement.

## Testing Expectations

- Every behavior change requires a focused Pest test.
- Capability tests must cover supported runtime, old PHP, old Laravel, missing SDK, and explicit disablement.
- Route tests must verify that unavailable AI Fix endpoints return a clear `503` without exposing provider internals.
- Dashboard tests must verify that unavailable features are explained and their actions are hidden.
- Preserve existing tests for Blade, React, Vue, crawler, state scripts, history, baseline, PDF, preview, and CLI behavior.

## Documentation Contract

The package README is the concise installation and feature reference. The website contains the full documentation. Keep these claims exact:

- core: PHP 8.2+, Laravel 10–13
- AI Fix: PHP 8.3+, Laravel 12+, optional `laravel/ai`
- AI Fix sends a limited source-code context and issue metadata to the configured external provider
- all non-AI features remain available when AI Fix is unsupported or disabled
- dashboard and CLI support WCAG 2.0, 2.1, and 2.2, with WCAG 2.0 as the backward-compatible default

Avoid describing optional or partial functionality as universally available.
