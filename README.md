# Lens for Laravel

**A local-first accessibility auditor for Laravel applications.**

Lens for Laravel scans your application with [axe-core](https://github.com/dequelabs/axe-core), renders JavaScript through [Spatie Browsershot](https://github.com/spatie/browsershot), maps violations back to source files, and can generate AI-assisted fixes for Blade, React, and Vue code.

**v3.0.0 development line:** selectable WCAG standards, URL-aware history, reusable state scripts, consistent local HTTPS behavior, complete five-language UI catalogs, and a safer optional AI integration for Blade, Livewire, Inertia, React, Vue, and mixed frontends.

**[Documentation & full feature overview -> lens.webcrafts.pl](https://lens.webcrafts.pl/)**

---

## Features

- **Axe-core scanning** - WCAG 2.x and best-practice checks through the industry-standard axe engine.
- **Selectable WCAG standard** - run cumulative WCAG 2.0, 2.1, or 2.2 rule sets in the dashboard and CLI; 2.0 remains the default for backward compatibility.
- **JavaScript rendering** - scans the hydrated browser DOM through Browsershot/Chromium.
- **Blade, React, and Vue source locator** - maps DOM violations back to `resources/views/**/*.blade.php` and frontend files under `resources/js`.
- **Source type labels** - results include `sourceType` values: `blade`, `react`, or `vue`.
- **Inertia-aware file discovery** - React/Vue pages under `resources/js/Pages/**` are included automatically.
- **Optional AI Fix assistant** - on PHP 8.3+ and Laravel 12+, generates reviewable fixes for Blade, React, and Vue through the optional `laravel/ai` SDK.
- **Diff preview before apply** - inspect AI changes before writing to disk.
- **Honest AI verification state** - applied suggestions remain counted and are marked as pending until a fresh axe-core scan verifies the result.
- **Whole-site crawler** - discovers pages from sitemaps and internal links.
- **SPA crawler mode** - optionally renders JavaScript while crawling React/Vue/Inertia apps.
- **Multi-URL scans** - scan selected URLs in a single dashboard or CLI run.
- **Interactive state scans** - execute clicks, waits, typing, select changes, and checkbox states before scanning.
- **Local HTTPS support** - optionally ignore self-signed certificate errors in local environments.
- **Scan history** - stores scan runs, issue counts, affected URLs, source locations, and trend data.
- **URL-aware scan comparison** - compare two historical scans by rule, normalized URL, interactive state, and selector to see new, fixed, and remaining issues without conflating the same selector across pages.
- **Baseline quality gate** - fail CI only when new accessibility regressions appear.
- **Element preview** - screenshot the page with the failing element highlighted.
- **PDF reports** - export audit results as a PDF.
- **CLI audits** - run `php artisan lens:audit` with WCAG selection, reusable state scripts, crawl mode, thresholds, and baseline gates.
- **IDE links** - open source locations in VS Code, Cursor, PhpStorm, or Sublime Text.
- **Developer dashboard** - zero build step dashboard using Alpine.js and Tailwind CSS via CDN.
- **Five interface languages** - package-owned dashboard, history, comparison, modal, recorder, PDF, and error text in English, Polish, Spanish, French, and German.

---

## Requirements

| Requirement | Version |
|---|---|
| PHP | ^8.2 |
| Laravel | ^10.0 \| ^11.0 \| ^12.0 \| ^13.0 |
| Node.js | Any recent LTS |
| Puppeteer | ^21 recommended |
| Chromium | Provided by Puppeteer or your environment |

Lens uses Browsershot to control Chromium. Install Puppeteer in the host application:

```bash
npm install puppeteer --save-dev
```

If your environment requires a custom Chromium, Node, or npm path, configure Browsershot through your application environment as you normally would.

AI Fix has a narrower runtime matrix than the core scanner:

| AI Fix requirement | Version |
|---|---|
| PHP | ^8.3 |
| Laravel | ^12.0 \| ^13.0 |
| Optional SDK | `laravel/ai` ^0.3.2 |

Laravel 10/11 and PHP 8.2 applications retain scanning, crawling, history, PDF, preview, source location, interactive states, and CLI support. The dashboard hides AI Fix actions and explains why they are unavailable.

---

## Installation

Install Lens as a development dependency:

```bash
composer require webcrafts-studio/lens-for-laravel --dev
```

The service provider is auto-discovered.

The core package intentionally does not require an AI SDK. On a supported runtime, install AI Fix separately when you want generated fixes:

```bash
composer require laravel/ai:^0.3.2 --dev
```

AI Fix is optional. Lens continues to work without this package.

Run migrations if you want scan history:

```bash
php artisan migrate
```

Publish the config when you want to customize behavior:

```bash
php artisan vendor:publish --tag="lens-for-laravel-config"
```

Optionally publish package views:

```bash
php artisan vendor:publish --tag="lens-for-laravel-views"
```

---

## Quick Start

Start your Laravel app and open:

```text
http://your-app.test/lens-for-laravel/dashboard
```

Enter a URL from the same host as `APP_URL`, then run a scan. Results include:

- WCAG level and impact
- failing DOM snippet
- CSS selector
- source file, line number, and source type when located
- Deque/WCAG documentation links

Choose **WCAG 2.0**, **2.1**, or **2.2** before starting the scan. Later versions include the rules from earlier versions plus axe-core rules for the newer success criteria. Existing installations continue to default to WCAG 2.0.
- element preview screenshot
- optional AI fix workflow

---

## Supported Frontends

### Blade

Blade support is the most direct path. Lens scans the rendered DOM and searches `resources/views/**/*.blade.php` for matching elements, IDs, names, classes, and selectors.

AI Fix can modify located `.blade.php` files under `resources/views`.

### Livewire

Livewire works through the rendered DOM and Blade source locator. For delayed hydration or UI updates, use:

```env
LENS_FOR_LARAVEL_SCAN_WAIT_MS=500
```

Automated scans only inspect the current browser state after page load. Interactive states such as open modals, validation errors, dropdowns, and tabs still need targeted URLs or manual review.

### React

Lens locates React source files under:

```text
resources/js/**/*.js
resources/js/**/*.jsx
resources/js/**/*.ts
resources/js/**/*.tsx
```

It supports common JSX/TSX patterns such as:

- static attributes: `id="logo"`, `name="email"`
- JSX expressions: `href={'/pricing'}`
- `className`
- selector variants like `primary-button`, `primaryButton`, and `PrimaryButton`
- Inertia pages under `resources/js/Pages/**`

AI Fix can modify supported React files under `resources/js`.

### Vue

Lens locates Vue single-file components under:

```text
resources/js/**/*.vue
```

It supports common Vue template patterns such as:

- static attributes: `class="logo"`, `href="/pricing"`
- bindings: `:href="'/pricing'"`, `v-bind:href="'/pricing'"`
- class object keys: `:class="{ active: isActive }"`

AI Fix can modify `.vue` files under `resources/js`.

### Inertia

Inertia React and Vue apps are supported through the React/Vue source locators. For route discovery in SPA-heavy apps, enable JavaScript crawling:

```env
LENS_FOR_LARAVEL_CRAWLER_RENDER_JAVASCRIPT=true
```

---

## Dashboard

The dashboard has two primary tabs.

### Scanner

Run scans in four modes:

- single URL
- multiple URLs
- whole website crawl
- interactive states

Each issue can be expanded to inspect the failing node, copy the selector, preview the element, open the source file in your editor, or request an AI fix.

Interactive state scans can be recorded visually in the dashboard. Click **Record** to open a recorder window, interact with the target page, create named states as you move through the flow, and send the generated script back to Lens. You can also paste or edit the raw script directly. The generated format stays copyable and CI-friendly:

```text
state: Navigation open
click: [data-menu-button]

state: Form validation
type: input[name="email"] => invalid@example.test
click: button[type="submit"]
wait: 300
```

Supported actions are `click`, `type`, `select`, `check`, `uncheck`, and `wait`.

Save the same script in a file to run it from the CLI:

```bash
php artisan lens:audit http://your-app.test --states=tests/accessibility/navigation.states
```

Interactive-state CLI scans support WCAG selection, level filters, thresholds, and baselines. They require one URL and cannot be combined with `--crawl`.

### History

History stores scan runs and issue metadata in your database. It supports:

- paginated scan history
- trend chart for recent scans
- scan details
- deleting old scans
- comparing two scans to identify new, fixed, and remaining issues

---

## Artisan Command

Run audits from the terminal:

```bash
# Audit the app root URL
php artisan lens:audit

# Audit specific URLs
php artisan lens:audit http://your-app.test/about http://your-app.test/contact

# Crawl and audit discovered internal pages
php artisan lens:audit --crawl

# Level A violations only
php artisan lens:audit --a

# Level A and AA violations
php artisan lens:audit --aa

# All levels, including AAA and best-practice
php artisan lens:audit --all

# Run the cumulative WCAG 2.2 rule set
php artisan lens:audit --wcag=2.2

# Execute a reusable interactive-state script
php artisan lens:audit http://your-app.test --states=tests/accessibility/navigation.states

# Fail with exit code 1 when violations exceed a threshold
php artisan lens:audit --threshold=10

# Save the current violations as a baseline
php artisan lens:audit --crawl --baseline

# Fail only when new violations appear compared to the baseline
php artisan lens:audit --crawl --fail-on-new

# Use a custom baseline file path
php artisan lens:audit --crawl --fail-on-new --baseline-file=.github/lens-baseline.json
```

The CLI uses the same scanner, crawler, interaction-script parser, source locator, and source type metadata as the dashboard. State labels are printed in the diagnostic table and preserved in baselines.

`--wcag=2.0`, `--wcag=2.1`, and `--wcag=2.2` select the standard version. This is independent from `--a`, `--aa`, and `--all`, which select the conformance levels shown in the result. The default standard is WCAG 2.0. When changing the standard used by a baseline workflow, create a fresh reviewed baseline.

### Baseline Quality Gate

Use the baseline gate when an existing application already has accessibility issues and you want CI to block only new regressions.

```bash
# Create or refresh the baseline after reviewing the current state
php artisan lens:audit --crawl --baseline

# In CI, compare the current scan against that baseline
php artisan lens:audit --crawl --fail-on-new
```

By default, Lens stores the baseline in:

```text
storage/app/lens-for-laravel/baseline.json
```

The comparison uses stable fingerprints based on the rule, normalized URL path, selector, and source file when available, so a different local or CI host does not invalidate the baseline.

### Local HTTPS Certificates

For local environments with self-signed certificates, such as DDEV or Laravel Valet, enable:

```env
LENS_FOR_LARAVEL_IGNORE_HTTPS_ERRORS=true
```

The default is `false`, so production-like scans stay strict unless you explicitly opt in.

---

## Configuration

Published config file:

```php
return [
    'route_prefix' => 'lens-for-laravel',

    'middleware' => ['web'],

    'enabled_environments' => [
        'local',
    ],

    'locale' => env('LENS_FOR_LARAVEL_LOCALE', app()->getLocale()),

    'fallback_locale' => env('LENS_FOR_LARAVEL_FALLBACK_LOCALE', 'en'),

    'supported_locales' => [
        'en' => 'English',
        'pl' => 'Polski',
        'es' => 'Español',
        'fr' => 'Français',
        'de' => 'Deutsch',
    ],

    'editor' => env('LENS_FOR_LARAVEL_EDITOR', 'vscode'),

    'crawl_max_pages' => env('LENS_FOR_LARAVEL_CRAWL_MAX_PAGES', 50),

    'crawler_render_javascript' => env('LENS_FOR_LARAVEL_CRAWLER_RENDER_JAVASCRIPT', false),

    'scan_wait_ms' => env('LENS_FOR_LARAVEL_SCAN_WAIT_MS', 0),

    'wcag_version' => env('LENS_FOR_LARAVEL_WCAG_VERSION', '2.0'),

    'baseline_path' => env('LENS_FOR_LARAVEL_BASELINE_PATH', storage_path('app/lens-for-laravel/baseline.json')),

    'ignore_https_errors' => env('LENS_FOR_LARAVEL_IGNORE_HTTPS_ERRORS', false),

    'ai_enabled' => env('LENS_FOR_LARAVEL_AI_ENABLED', true),

    'ai_provider' => env('LENS_FOR_LARAVEL_AI_PROVIDER', 'gemini'),
];
```

### Environment Options

```env
LENS_FOR_LARAVEL_EDITOR=vscode
LENS_FOR_LARAVEL_LOCALE=en
LENS_FOR_LARAVEL_FALLBACK_LOCALE=en
LENS_FOR_LARAVEL_CRAWL_MAX_PAGES=50
LENS_FOR_LARAVEL_CRAWLER_RENDER_JAVASCRIPT=false
LENS_FOR_LARAVEL_SCAN_WAIT_MS=0
LENS_FOR_LARAVEL_WCAG_VERSION=2.0
LENS_FOR_LARAVEL_BASELINE_PATH=storage/app/lens-for-laravel/baseline.json
LENS_FOR_LARAVEL_IGNORE_HTTPS_ERRORS=false
LENS_FOR_LARAVEL_AI_ENABLED=true
LENS_FOR_LARAVEL_AI_PROVIDER=gemini
```

Set `LENS_FOR_LARAVEL_IGNORE_HTTPS_ERRORS=true` only for trusted local environments with self-signed certificates. In v3.0 the setting consistently covers axe scans, sitemap and page requests made by the crawler, optional JavaScript-rendered crawling, and element preview screenshots. Its default remains `false`.

### Interface languages

Version 3 ships complete package-owned interface catalogs for English, Polish, Spanish, French, and German. The selected language covers the scanner, history and URL-aware comparisons, AI Fix and preview modals, interactive-state recorder, PDF reports, chart labels, and package-generated browser, route, interaction-script, baseline, and CLI error messages. The dashboard language switcher stores its choice in the session; exported PDF reports use that same language. Console errors use the Laravel application locale active for the command.

`locale` defines the initial language, `fallback_locale` is used when a translation is unavailable, and `supported_locales` controls the choices displayed in the dashboard. Accessibility-rule descriptions returned by axe-core and framework validation messages can still follow the language supplied by those upstream libraries rather than Lens's catalog.

Supported editors:

- `vscode`
- `cursor`
- `phpstorm`
- `sublime`
- `none`

Supported AI providers:

- `gemini`
- `openai`
- `anthropic`

Disable AI Fix explicitly while keeping all scanning features enabled:

```env
LENS_FOR_LARAVEL_AI_ENABLED=false
```

---

## Crawling

Whole-site scans discover URLs in this order:

1. `sitemap.xml`
2. `sitemap_index.xml`
3. `sitemaps/sitemap.xml`
4. internal `<a href>` links

By default, crawling uses Laravel's HTTP client and parses the initial HTML. This is fast and works well for Blade, Livewire, and server-rendered pages.

For SPA or Inertia apps where links are rendered after JavaScript hydration:

```env
LENS_FOR_LARAVEL_CRAWLER_RENDER_JAVASCRIPT=true
```

With that enabled, Lens attempts to render each crawled page in Chromium and collect links from the hydrated DOM. If browser crawling fails or finds no links, it falls back to the HTTP crawler.

Limit the crawl size with:

```env
LENS_FOR_LARAVEL_CRAWL_MAX_PAGES=100
```

---

## AI Fix

AI Fix is available only when all of the following are true:

- PHP 8.3 or newer
- Laravel 12 or newer
- the optional `laravel/ai` package is installed
- `LENS_FOR_LARAVEL_AI_ENABLED` is not set to `false`

On older supported applications, only AI Fix is disabled. The accessibility scanner and every non-AI feature remain available.

The AI Fix workflow:

1. Lens locates the source file and line.
2. It extracts the smallest relevant element or component instead of an arbitrary line window.
3. It sends the issue, failing DOM snippet, WCAG tags, and selected source fragment to the configured AI provider.
4. A dedicated accessibility agent returns a minimal replacement and explanation.
5. The dashboard shows a diff preview.
6. You can accept and apply the change.
7. The issue is immediately marked **AI Fix applied — pending re-scan** while remaining in the violation counts until a new axe-core scan verifies the result.

The v3.0 agent uses a deterministic temperature of `0`, a `12000`-token output ceiling, and a reduced Gemini thinking budget. Lens does not select or expose a model: `laravel/ai` uses the default model configured for the chosen provider. If the provider reaches its token limit or returns malformed structured output, Lens performs one controlled retry. Persistent failures produce a safe, understandable message; provider, resolved model, finish reason, and token usage are recorded in the application log without logging the submitted source fragment.

> **Privacy:** AI Fix sends the failing DOM snippet, accessibility issue details, WCAG tags, and a bounded element/component source fragment to the configured Gemini, OpenAI, or Anthropic provider. It does not send the entire repository. Review the selected source context for secrets or sensitive information before requesting a fix, and follow the chosen provider's data-handling policy.

Configure provider credentials:

```env
LENS_FOR_LARAVEL_AI_PROVIDER=gemini
GEMINI_API_KEY=your-key-here

# or
LENS_FOR_LARAVEL_AI_PROVIDER=openai
OPENAI_API_KEY=your-key-here

# or
LENS_FOR_LARAVEL_AI_PROVIDER=anthropic
ANTHROPIC_API_KEY=your-key-here
```

Supported writable files:

- `resources/views/**/*.blade.php`
- `resources/js/**/*.js`
- `resources/js/**/*.jsx`
- `resources/js/**/*.ts`
- `resources/js/**/*.tsx`
- `resources/js/**/*.vue`

AI Fix does not write outside those paths.

---

## IDE Integration

When a source location is found, click it in the dashboard to open your editor at the exact line.

```env
LENS_FOR_LARAVEL_EDITOR=vscode
```

Use `none` to disable editor links:

```env
LENS_FOR_LARAVEL_EDITOR=none
```

---

## Scan Output

Each issue includes fields like:

```json
{
  "id": "image-alt",
  "impact": "critical",
  "description": "Images must have alternate text",
  "helpUrl": "https://dequeuniversity.com/rules/axe/...",
  "htmlSnippet": "<img class=\"logo\" src=\"/logo.png\">",
  "selector": ".logo",
  "tags": ["wcag2a"],
  "url": "http://your-app.test",
  "fileName": "js/Components/Logo.vue",
  "lineNumber": 12,
  "sourceType": "vue"
}
```

`sourceType` can be:

- `blade`
- `react`
- `vue`
- `null` when no source location is found

---

## Security

Lens is intended for local and controlled development environments.

Built-in protections:

- Dashboard access is restricted by `enabled_environments`.
- Scan URLs must use HTTP or HTTPS.
- Scan URLs must match the host configured in `APP_URL`.
- External domain scanning is blocked.
- AI Fix apply rejects path traversal.
- AI Fix writes only to supported Blade/React/Vue source paths.
- AI Fix blocks generated code containing server-side execution functions such as `shell_exec`, `system`, `exec`, `passthru`, `proc_open`, `popen`, and `eval`.
- AI Fix blocks newly introduced raw PHP open tags unless they were already present in the original code block.
- Fix writes use `LOCK_EX`.
- Scan, crawl, preview, fix, history, and report endpoints use throttling where appropriate.

Recommended production posture:

```php
'enabled_environments' => ['local'],
```

If you enable Lens on staging, protect the route with authentication middleware:

```php
'middleware' => ['web', 'auth'],
```

---

## Known Limitations

axe-core automates many high-confidence accessibility checks, but neither axe-core nor Lens can determine full WCAG conformance. A clean scan is evidence from automated checks, not proof that the application is fully accessible.

Source location is heuristic. Lens can locate many common Blade, React, Vue, and Inertia patterns, but it may miss or misidentify:

- deeply abstracted components
- custom components that render HTML internally, such as `<LogoImage />`
- dynamic class builders with no literal class or recognizable variant
- CSS module keys that do not resemble the final generated class
- runtime-generated attributes
- elements rendered only after user interaction

For interactive states, run targeted scans after exposing the state, add dedicated URLs, or combine Lens with manual QA.

Always complement Lens with:

- keyboard navigation testing
- screen reader testing with NVDA, JAWS, or VoiceOver
- manual form validation checks
- modal, menu, dropdown, accordion, and tab interaction checks
- cognitive and usability review

---

## Upgrade Notes for v3.0.0

Version 3 is the current development line. Completed v3 changes include:

- selectable WCAG 2.0, 2.1, and 2.2 standards in the dashboard and CLI
- WCAG 2.0 as the backward-compatible default
- persisted WCAG version metadata in scan history, comparisons, baselines, and PDF reports
- URL-aware history comparisons that distinguish identical rules and selectors on different pages
- reusable interactive-state scripts in the CLI through `--states=path`
- consistent `ignore_https_errors` handling for scans, HTTP/browser crawling, and previews
- core support for PHP 8.2+ and Laravel 10–13
- AI Fix isolated as an optional feature requiring PHP 8.3+, Laravel 12+, and `laravel/ai`
- stabilized AI Fix with semantic source fragments, minimal replacements, bounded Gemini thinking, one controlled structured-output retry, safe errors, and provider/model/token diagnostics
- immediate pending-verification status on issues changed by AI Fix, without claiming success before a new axe-core scan
- complete English, Polish, Spanish, French, and German catalogs for package-owned dashboard, history, comparison, modal, PDF, and error text

After upgrading to v3:

```bash
php artisan migrate
```

If you published the config before v3.0.0, add:

```php
'wcag_version' => env('LENS_FOR_LARAVEL_WCAG_VERSION', '2.0'),
'ai_enabled' => env('LENS_FOR_LARAVEL_AI_ENABLED', true),
```

Install the optional AI SDK only on a supported runtime when AI Fix is needed:

```bash
composer require laravel/ai:^0.3.2 --dev
```

### Historical: Upgrade Notes for v2.0.0

Version 2 adds major frontend support and persistence features:

- React source locating and AI Fix
- Vue source locating and AI Fix
- Inertia-friendly source discovery
- `sourceType` metadata
- scan history tables
- scan comparison
- SPA crawler option
- scan wait option

After upgrading:

```bash
php artisan migrate
php artisan vendor:publish --tag="lens-for-laravel-config"
```

If you already published the config, manually add:

```php
'crawler_render_javascript' => env('LENS_FOR_LARAVEL_CRAWLER_RENDER_JAVASCRIPT', false),
'scan_wait_ms' => env('LENS_FOR_LARAVEL_SCAN_WAIT_MS', 0),
```

---

## Testing This Package

```bash
vendor/bin/pint --dirty
vendor/bin/pest
```

---

## License

The MIT License (MIT). See [LICENSE.md](LICENSE.md) for details.
