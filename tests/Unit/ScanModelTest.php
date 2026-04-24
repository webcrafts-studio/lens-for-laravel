<?php

use LensForLaravel\LensForLaravel\Models\Scan;
use LensForLaravel\LensForLaravel\Models\ScanIssue;

test('scan has many issues relationship', function () {
    $scan = Scan::create([
        'url' => 'http://localhost',
        'scan_mode' => 'single',
        'urls_scanned' => ['http://localhost'],
        'total_issues' => 1,
        'level_a_count' => 1,
        'level_aa_count' => 0,
        'level_aaa_count' => 0,
    ]);

    $scan->issues()->create([
        'rule_id' => 'image-alt',
        'impact' => 'critical',
        'description' => 'Images must have alternate text',
        'tags' => ['wcag2a'],
        'selector' => 'img',
    ]);

    expect($scan->issues)->toHaveCount(1);
    expect($scan->issues->first())->toBeInstanceOf(ScanIssue::class);
});

test('scan issue belongs to scan relationship', function () {
    $scan = Scan::create([
        'url' => 'http://localhost',
        'scan_mode' => 'single',
        'urls_scanned' => [],
        'total_issues' => 0,
        'level_a_count' => 0,
        'level_aa_count' => 0,
        'level_aaa_count' => 0,
    ]);

    $issue = $scan->issues()->create([
        'rule_id' => 'color-contrast',
        'impact' => 'serious',
        'tags' => ['wcag2aa'],
    ]);

    expect($issue->scan)->toBeInstanceOf(Scan::class);
    expect($issue->scan->id)->toBe($scan->id);
});

test('scan casts urls_scanned to array', function () {
    $scan = Scan::create([
        'url' => 'http://localhost',
        'scan_mode' => 'website',
        'urls_scanned' => ['http://localhost', 'http://localhost/about'],
        'total_issues' => 0,
        'level_a_count' => 0,
        'level_aa_count' => 0,
        'level_aaa_count' => 0,
    ]);

    $fresh = Scan::find($scan->id);
    expect($fresh->urls_scanned)->toBeArray();
    expect($fresh->urls_scanned)->toHaveCount(2);
});

test('scan casts integer counts', function () {
    $scan = Scan::create([
        'url' => 'http://localhost',
        'scan_mode' => 'single',
        'urls_scanned' => [],
        'total_issues' => '5',
        'level_a_count' => '2',
        'level_aa_count' => '3',
        'level_aaa_count' => '0',
    ]);

    $fresh = Scan::find($scan->id);
    expect($fresh->total_issues)->toBeInt();
    expect($fresh->level_a_count)->toBeInt();
    expect($fresh->level_aa_count)->toBeInt();
    expect($fresh->level_aaa_count)->toBeInt();
});

test('scan issue casts tags to array', function () {
    $scan = Scan::create([
        'url' => 'http://localhost',
        'scan_mode' => 'single',
        'urls_scanned' => [],
        'total_issues' => 0,
        'level_a_count' => 0,
        'level_aa_count' => 0,
        'level_aaa_count' => 0,
    ]);

    $issue = $scan->issues()->create([
        'rule_id' => 'test-rule',
        'tags' => ['wcag2a', 'best-practice'],
    ]);

    $fresh = ScanIssue::find($issue->id);
    expect($fresh->tags)->toBeArray();
    expect($fresh->tags)->toBe(['wcag2a', 'best-practice']);
});

test('deleting scan cascades to issues', function () {
    $scan = Scan::create([
        'url' => 'http://localhost',
        'scan_mode' => 'single',
        'urls_scanned' => [],
        'total_issues' => 1,
        'level_a_count' => 0,
        'level_aa_count' => 0,
        'level_aaa_count' => 0,
    ]);

    $scan->issues()->create([
        'rule_id' => 'test-rule',
        'impact' => 'minor',
    ]);

    expect(ScanIssue::count())->toBe(1);

    $scan->delete();

    expect(ScanIssue::count())->toBe(0);
});
