# Lens for Laravel

**A local-first accessibility auditor for Laravel applications.**

Lens for Laravel scans your application with [axe-core](https://github.com/dequelabs/axe-core), renders JavaScript through [Spatie Browsershot](https://github.com/spatie/browsershot), maps violations back to source files, and can generate AI-assisted fixes for Blade, React, and Vue code.

**v2.0.0 focus:** Laravel teams using Blade, Livewire, Inertia, React, Vue, or mixed frontends.

**[Documentation & full feature overview -> lens.webcrafts.pl](https://lens.webcrafts.pl/)**

---

## Features

- **Axe-core scanning** - WCAG 2.x and best-practice checks through the industry-standard axe engine.
- **JavaScript rendering** - scans the hydrated browser DOM through Browsershot/Chromium.
- **Blade, React, and Vue source locator** - maps DOM violations back to `resources/views/**/*.blade.php` and frontend files under `resources/js`.
- **Source type labels** - results include `sourceType` values: `blade`, `react`, or `vue`.
- **Inertia-aware file discovery** - React/Vue pages under `resources/js/Pages/**` are included automatically.
- **AI Fix assistant** - generates reviewable fixes and applies them to Blade, React, and Vue files.
- **Diff preview before apply** - inspect AI changes before writing to disk.
- **Whole-site crawler** - discovers pages from sitemaps and internal links.
- **SPA crawler mode** - optionally renders JavaScript while crawling React/Vue/Inertia apps.
- **Multi-URL scans** - scan selected URLs in a single dashboard or CLI run.
- **Interactive state scans** - execute clicks, waits, typing, select changes, and checkbox states before scanning.
- **Local HTTPS support** - optionally ignore self-signed certificate errors in local environments.
- **Scan history** - stores scan runs, issue counts, affected URLs, source locations, and trend data.
- **Scan comparison** - compare two historical scans to see new, fixed, and remaining issues.
- **Baseline quality gate** - fail CI only when new accessibility regressions appear.
- **Element preview** - screenshot the page with the failing element highlighted.
- **PDF reports** - export audit results as a PDF.
- **CLI audits** - run `php artisan lens:audit` with WCAG filters, crawl mode, and CI thresholds.
- **IDE links** - open source locations in VS Code, Cursor, PhpStorm, or Sublime Text.
- **Developer dashboard** - zero build step dashboard using Alpine.js and Tailwind CSS via CDN.

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

---

## Installation

Install Lens as a development dependency:

```bash
composer require webcrafts-studio/lens-for-laravel --dev
```

The service provider is auto-discovered.

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

# Fail with exit code 1 when violations exceed a threshold
php artisan lens:audit --threshold=10

# Save the current violations as a baseline
php artisan lens:audit --crawl --baseline

# Fail only when new violations appear compared to the baseline
php artisan lens:audit --crawl --fail-on-new

# Use a custom baseline file path
php artisan lens:audit --crawl --fail-on-new --baseline-file=.github/lens-baseline.json
```

The CLI uses the same scanner, crawler, source locator, and source type metadata as the dashboard.

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

    'editor' => env('LENS_FOR_LARAVEL_EDITOR', 'vscode'),

    'crawl_max_pages' => env('LENS_FOR_LARAVEL_CRAWL_MAX_PAGES', 50),

    'crawler_render_javascript' => env('LENS_FOR_LARAVEL_CRAWLER_RENDER_JAVASCRIPT', false),

    'scan_wait_ms' => env('LENS_FOR_LARAVEL_SCAN_WAIT_MS', 0),

    'baseline_path' => env('LENS_FOR_LARAVEL_BASELINE_PATH', storage_path('app/lens-for-laravel/baseline.json')),

    'ignore_https_errors' => env('LENS_FOR_LARAVEL_IGNORE_HTTPS_ERRORS', false),

    'ai_provider' => env('LENS_FOR_LARAVEL_AI_PROVIDER', 'gemini'),
];
```

### Environment Options

```env
LENS_FOR_LARAVEL_EDITOR=vscode
LENS_FOR_LARAVEL_CRAWL_MAX_PAGES=50
LENS_FOR_LARAVEL_CRAWLER_RENDER_JAVASCRIPT=false
LENS_FOR_LARAVEL_SCAN_WAIT_MS=0
LENS_FOR_LARAVEL_BASELINE_PATH=storage/app/lens-for-laravel/baseline.json
LENS_FOR_LARAVEL_IGNORE_HTTPS_ERRORS=false
LENS_FOR_LARAVEL_AI_PROVIDER=gemini
```

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

The AI Fix workflow:

1. Lens locates the source file and line.
2. It reads a context window around the issue.
3. It sends the issue, failing DOM snippet, WCAG tags, and source context to the configured AI provider.
4. It returns an explanation and full replacement code block.
5. The dashboard shows a diff preview.
6. You can accept and apply the change.

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

Automated accessibility testing cannot prove full compliance. Axe-core usually detects only a portion of WCAG issues.

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

## Upgrade Notes for v2.0.0

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
