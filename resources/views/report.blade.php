<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{{ __('lens-for-laravel::messages.report.title') }}</title>
<style>
    * { box-sizing: border-box; margin: 0; padding: 0; }

    body {
        font-family: 'Courier New', Courier, monospace;
        font-size: 11px;
        color: #111;
        background: #fff;
        padding: 40px 48px;
        line-height: 1.5;
    }

    /* ── Header ── */
    .header {
        border-bottom: 3px solid #111;
        padding-bottom: 20px;
        margin-bottom: 28px;
    }
    .header-top {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
    }
    .brand {
        font-size: 20px;
        font-weight: 700;
        letter-spacing: 0.15em;
        text-transform: uppercase;
    }
    .brand span { color: #E11D48; }
    .meta {
        text-align: right;
        font-size: 10px;
        color: #555;
        line-height: 1.8;
    }
    .scanned-url {
        margin-top: 10px;
        font-size: 11px;
        color: #444;
    }
    .scanned-url strong { color: #111; }

    /* ── Summary ── */
    .summary {
        display: grid;
        grid-template-columns: repeat(5, 1fr);
        gap: 12px;
        margin-bottom: 32px;
    }
    .stat-box {
        border: 2px solid #d1d5db;
        background: #fff;
        color: #111;
        padding: 16px 18px;
    }
    /* All — thick black border */
    .stat-box.all {
        border-color: #111;
        border-width: 3px;
    }
    /* A Level — red border */
    .stat-box.level-a {
        border-color: #E11D48;
    }
    /* AA Level — white, solid border */
    .stat-box.level-aa {
        border-color: #111;
        border-style: solid;
    }
    /* AAA Level — white, dashed border */
    .stat-box.level-aaa {
        border-color: #9ca3af;
        border-style: dashed;
    }
    /* Other — white, dotted border */
    .stat-box.other {
        border-color: #9ca3af;
        border-style: dotted;
    }
    .stat-label {
        font-size: 8px;
        font-weight: 700;
        letter-spacing: 0.12em;
        text-transform: uppercase;
        border-bottom: 1px solid rgba(0,0,0,0.15);
        padding-bottom: 6px;
        margin-bottom: 8px;
        color: #111;
    }
    .stat-box.level-a .stat-label { border-color: rgba(225,29,72,0.2); }
    .stat-value {
        font-size: 32px;
        font-weight: 700;
        line-height: 1;
        letter-spacing: -0.02em;
    }

    /* ── Section title ── */
    .section-title {
        font-size: 9px;
        font-weight: 700;
        letter-spacing: 0.18em;
        text-transform: uppercase;
        color: #555;
        border-bottom: 1px solid #ddd;
        padding-bottom: 6px;
        margin-bottom: 16px;
    }
    .section-title span { color: #111; }

    /* ── Issue card ── */
    .issue {
        border: 1.5px solid #ddd;
        margin-bottom: 14px;
        page-break-inside: avoid;
    }
    .issue-header {
        display: flex;
        align-items: stretch;
        border-bottom: 1.5px solid #ddd;
    }
    .impact-badge {
        writing-mode: vertical-rl;
        text-orientation: mixed;
        transform: rotate(180deg);
        font-size: 7px;
        font-weight: 700;
        letter-spacing: 0.14em;
        text-transform: uppercase;
        padding: 8px 5px;
        min-width: 22px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #fff;
    }
    .impact-badge.critical { background: #E11D48; }
    .impact-badge.serious  { background: #EA580C; }
    .impact-badge.moderate { background: #CA8A04; }
    .impact-badge.minor    { background: #65A30D; }
    .impact-badge.unknown  { background: #6B7280; }

    .issue-title-wrap {
        flex: 1;
        padding: 10px 14px;
    }
    .issue-id {
        font-size: 8px;
        color: #888;
        letter-spacing: 0.1em;
        text-transform: uppercase;
        margin-bottom: 3px;
    }
    .issue-description {
        font-size: 11px;
        font-weight: 700;
        color: #111;
        line-height: 1.4;
    }

    .issue-tags {
        padding: 6px 14px;
        display: flex;
        flex-wrap: wrap;
        gap: 5px;
        border-bottom: 1px solid #eee;
    }
    .tag {
        font-size: 8px;
        font-weight: 700;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        border: 1px solid #ccc;
        padding: 1px 5px;
        color: #444;
    }
    .tag.wcag2a   { border-color: #E11D48; color: #E11D48; }
    .tag.wcag2aa  { border-color: #EA580C; color: #EA580C; }
    .tag.wcag2aaa { border-color: #CA8A04; color: #CA8A04; }

    .issue-body {
        padding: 10px 14px;
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 10px;
    }
    .issue-body.full { grid-template-columns: 1fr; }

    .field-label {
        font-size: 8px;
        font-weight: 700;
        letter-spacing: 0.12em;
        text-transform: uppercase;
        color: #888;
        margin-bottom: 4px;
    }
    .field-value {
        font-size: 10px;
        color: #111;
        word-break: break-all;
    }
    .code-block {
        background: #f5f5f5;
        border: 1px solid #e0e0e0;
        padding: 6px 8px;
        font-size: 9px;
        word-break: break-all;
        white-space: pre-wrap;
    }
    .help-link {
        font-size: 9px;
        color: #888;
        word-break: break-all;
    }

    /* ── URL label (multi-page scans) ── */
    .page-url-label {
        font-size: 8px;
        color: #aaa;
        letter-spacing: 0.06em;
        margin-top: 2px;
    }

    /* ── Footer ── */
    .footer {
        margin-top: 36px;
        padding-top: 14px;
        border-top: 1px solid #ddd;
        display: flex;
        justify-content: space-between;
        font-size: 9px;
        color: #aaa;
    }

    @page { margin: 0; }
    @media print {
        body { padding: 28px 36px; }
    }
</style>
</head>
<body>

{{-- ── Header ── --}}
<div class="header">
    <div class="header-top">
        <div class="brand">Lens for <span>Laravel</span></div>
        <div class="meta">
            <div>{{ __('lens-for-laravel::messages.report.audit_report') }}</div>
            <div>{{ __('lens-for-laravel::messages.report.generated') }}: {{ $generatedAt->format('Y-m-d H:i') }}</div>
        </div>
    </div>
    <div class="scanned-url">
        <strong>{{ __('lens-for-laravel::messages.report.target') }}:</strong> {{ $url }}
        <span style="margin-left: 16px"><strong>{{ __('lens-for-laravel::messages.report.standard') }}:</strong> WCAG {{ $wcagVersion }}</span>
    </div>
</div>

{{-- ── Summary ── --}}
@php
    $col     = collect($issues);
    $total   = $col->count();
    $levelA  = $col->filter(fn($i) => \LensForLaravel\LensForLaravel\Support\Wcag::level($i['tags'] ?? []) === 'a')->count();
    $levelAA = $col->filter(fn($i) => \LensForLaravel\LensForLaravel\Support\Wcag::level($i['tags'] ?? []) === 'aa')->count();
    $levelAAA= $col->filter(fn($i) => \LensForLaravel\LensForLaravel\Support\Wcag::level($i['tags'] ?? []) === 'aaa')->count();
    $other   = $col->filter(fn($i) => \LensForLaravel\LensForLaravel\Support\Wcag::level($i['tags'] ?? []) === 'other')->count();
@endphp

<div class="summary">
    <div class="stat-box all">
        <div class="stat-label">{{ __('lens-for-laravel::messages.common.all') }}</div>
        <div class="stat-value">{{ $total }}</div>
    </div>
    <div class="stat-box level-a">
        <div class="stat-label">{{ __('lens-for-laravel::messages.common.level_a') }}</div>
        <div class="stat-value">{{ $levelA }}</div>
    </div>
    <div class="stat-box level-aa">
        <div class="stat-label">{{ __('lens-for-laravel::messages.common.level_aa') }}</div>
        <div class="stat-value">{{ $levelAA }}</div>
    </div>
    <div class="stat-box level-aaa">
        <div class="stat-label">{{ __('lens-for-laravel::messages.common.level_aaa') }}</div>
        <div class="stat-value">{{ $levelAAA }}</div>
    </div>
    <div class="stat-box other">
        <div class="stat-label">{{ __('lens-for-laravel::messages.common.other') }}</div>
        <div class="stat-value">{{ $other }}</div>
    </div>
</div>

{{-- ── Issues ── --}}
<div class="section-title"><span>>>></span> {{ __('lens-for-laravel::messages.report.violations') }} ({{ $total }})</div>

@forelse($issues as $issue)
    @php
        $impact  = $issue['impact'] ?? 'unknown';
        $wcagTags = array_filter($issue['tags'] ?? [], fn($t) => str_starts_with($t, 'wcag'));
    @endphp
    <div class="issue">
        <div class="issue-header">
            <div class="impact-badge {{ $impact }}">{{ __('lens-for-laravel::messages.report.impact.'.$impact) }}</div>
            <div class="issue-title-wrap">
                <div class="issue-id">{{ $issue['id'] ?? '' }}</div>
                <div class="issue-description">{{ $issue['description'] ?? '' }}</div>
                @if(!empty($issue['url']))
                    <div class="page-url-label">{{ $issue['url'] }}</div>
                @endif
                @if(!empty($issue['stateLabel']))
                    <div class="page-url-label">{{ __('lens-for-laravel::messages.report.state') }}: {{ $issue['stateLabel'] }}</div>
                @endif
            </div>
        </div>

        @if(!empty($wcagTags))
        <div class="issue-tags">
            @foreach($wcagTags as $tag)
                <span class="tag {{ $tag }}">{{ strtoupper($tag) }}</span>
            @endforeach
        </div>
        @endif

        <div class="issue-body">
            @if(!empty($issue['htmlSnippet']))
            <div>
                <div class="field-label">{{ __('lens-for-laravel::messages.report.html_snippet') }}</div>
                <div class="code-block">{{ $issue['htmlSnippet'] }}</div>
            </div>
            @endif

            <div>
                @if(!empty($issue['selector']))
                <div style="margin-bottom:8px">
                    <div class="field-label">{{ __('lens-for-laravel::messages.report.css_selector') }}</div>
                    <div class="code-block">{{ $issue['selector'] }}</div>
                </div>
                @endif

                @if(!empty($issue['fileName']))
                <div style="margin-bottom:8px">
                    <div class="field-label">{{ __('lens-for-laravel::messages.report.source_location') }}</div>
                    <div class="field-value">{{ $issue['fileName'] }}:{{ $issue['lineNumber'] ?? '?' }}</div>
                </div>
                @endif

                @if(!empty($issue['helpUrl']))
                <div>
                    <div class="field-label">{{ __('lens-for-laravel::messages.report.reference') }}</div>
                    <div class="help-link">{{ $issue['helpUrl'] }}</div>
                </div>
                @endif
            </div>
        </div>
    </div>
@empty
    <p style="color:#888;font-size:11px;">{{ __('lens-for-laravel::messages.report.no_violations') }}</p>
@endforelse

{{-- ── Footer ── --}}
<div class="footer">
    <span>{{ __('lens-for-laravel::messages.report.footer') }}</span>
    <span>{{ $generatedAt->format('Y-m-d') }}</span>
</div>

</body>
</html>
