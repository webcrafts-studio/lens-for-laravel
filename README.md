# Lens for Laravel

**A plug-and-play accessibility auditor for Laravel applications.**

Lens for Laravel dynamically scans your local application for WCAG compliance using [Axe-core](https://github.com/dequelabs/axe-core) and attempts to reverse-engineer failing CSS selectors back to the exact **Blade file and line number** causing the issue.

**[Documentation & full feature overview → lens.webcrafts.pl](https://lens.webcrafts.pl/)**

---

## Features

- **Zero frontend build step** — uses Alpine.js and Tailwind CSS via CDN; works immediately after installation
- **Powered by Axe-core** — industry-standard accessibility testing engine covering WCAG 2.x rules
- **Blade + React source locator** — maps compiled HTML violations back to `resources/views/**/*.blade.php` or React files under `resources/js`
- **WCAG level filtering** — view issues by Level A, AA, AAA, or best-practice separately
- **Whole-site crawler** — discovers pages via sitemap or link-crawling and scans up to a configurable limit
- **Multi-URL scanning** — target specific pages in a single run
- **AI-powered fix suggestions** — generates diff previews and applies fixes directly to Blade and React source files (supports Gemini, OpenAI, Anthropic)
- **IDE integration** — click any source location in the dashboard to open the file at the exact line in VSCode, Cursor, PhpStorm, or Sublime Text
- **Element preview** — takes a screenshot of the page with the offending element highlighted
- **PDF reports** — export full accessibility audit results as a formatted PDF
- **Artisan command** — run audits from the CLI with `lens:audit`, with support for thresholds and CI integration
- **Dark-mode dashboard** — clean developer-focused UI with direct links to WCAG and Deque rule documentation

---

## Requirements

| Requirement | Version |
|---|---|
| PHP | ^8.2 |
| Laravel | ^10.0 \| ^11.0 \| ^12.0 \| ^13.0 |
| Node.js | any recent LTS |
| Puppeteer | ^21 |

Lens for Laravel uses [Spatie Browsershot](https://github.com/spatie/browsershot) to render JavaScript and execute Axe-core, which requires a Chromium instance managed by Puppeteer.

Install Puppeteer as a local dev dependency in your application:

```bash
npm install puppeteer --save-dev
```

---

## Installation

Install as a development-only Composer dependency:

```bash
composer require webcrafts-studio/lens-for-laravel --dev
```

The package auto-discovers and registers its service provider. No additional configuration is required to get started.

---

## Usage

### Dashboard

Start your Laravel development server and navigate to:

```
http://your-app.test/lens-for-laravel/dashboard
```

Enter a URL from your local application and click **Scan Now**. Results are grouped by WCAG level and display the violation description, impacted element, estimated source file location, and links to relevant documentation.

### Artisan Command

Run accessibility audits directly from the terminal:

```bash
# Audit the application root URL
php artisan lens:audit

# Audit specific pages
php artisan lens:audit http://your-app.test/about http://your-app.test/contact

# Crawl the entire site and audit all discovered pages
php artisan lens:audit --crawl

# Filter by WCAG level
php artisan lens:audit --a      # Level A violations only
php artisan lens:audit --aa     # Level A and AA violations
php artisan lens:audit --all    # All levels including AAA and best-practice (default)

# Fail the command (exit code 1) if violations exceed a threshold — useful in CI
php artisan lens:audit --threshold=10
```

---

## Configuration

Publish the configuration file to customise the package behaviour:

```bash
php artisan vendor:publish --tag="lens-for-laravel-config"
```

This creates `config/lens-for-laravel.php`:

```php
return [
    // URL prefix for the dashboard routes
    // Default: 'lens-for-laravel'
    'route_prefix' => 'lens-for-laravel',

    // Middleware applied to all dashboard routes
    'middleware' => ['web'],

    // Environments where the dashboard is accessible
    // Add 'staging' here if you want to use it on a staging server
    'enabled_environments' => ['local'],

    // Editor opened when clicking a source location link
    // Supported: 'vscode', 'cursor', 'phpstorm', 'sublime', 'none'
    'editor' => env('LENS_FOR_LARAVEL_EDITOR', 'vscode'),

    // Maximum number of pages to crawl in whole-site scan mode
    'crawl_max_pages' => env('LENS_FOR_LARAVEL_CRAWL_MAX_PAGES', 50),

    // AI provider used for generating code fix suggestions
    // Supported: 'gemini', 'openai', 'anthropic'
    'ai_provider' => env('LENS_FOR_LARAVEL_AI_PROVIDER', 'gemini'),
];
```

---

## AI Fix Suggestions

The dashboard includes an AI-powered fix assistant. When a violation is expanded, you can request a suggested fix for the affected Blade or React snippet. The assistant returns a unified diff that can be previewed and applied directly to the file with a single click.

Configure your preferred provider and API key in `.env`:

```env
LENS_FOR_LARAVEL_AI_PROVIDER=gemini   # or openai, anthropic
GEMINI_API_KEY=your-key-here
# OPENAI_API_KEY=your-key-here
# ANTHROPIC_API_KEY=your-key-here
```

---

## Security

The dashboard enforces the following protections:

- **Environment restriction** — only available in environments listed in `enabled_environments` (default: `local`)
- **Domain restriction** — the scan endpoint only accepts URLs on the same host as `APP_URL`; scanning external domains is blocked
- **Path traversal prevention** — the fix-apply endpoint restricts file writes to Blade files in `resources/views` and React files in `resources/js`

---

## Disclaimer

Automated accessibility testing with Axe-core typically detects **20–30% of total WCAG violations**. Passing a scan in Lens for Laravel does not constitute full accessibility compliance and does not guarantee conformance with the ADA, Section 508, or the European Accessibility Act.

Always complement automated testing with:

- Manual keyboard navigation testing
- Screen reader testing (NVDA, JAWS, VoiceOver)
- Cognitive and usability walkthroughs

---

## License

The MIT License (MIT). See [LICENSE.md](LICENSE.md) for details.
