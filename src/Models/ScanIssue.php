<?php

namespace LensForLaravel\LensForLaravel\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScanIssue extends Model
{
    public $timestamps = false;

    protected $table = 'lens_scan_issues';

    protected $fillable = [
        'scan_id',
        'rule_id',
        'impact',
        'description',
        'help_url',
        'html_snippet',
        'selector',
        'tags',
        'url',
        'file_name',
        'line_number',
    ];

    protected $casts = [
        'tags' => 'array',
        'line_number' => 'integer',
    ];

    public function scan(): BelongsTo
    {
        return $this->belongsTo(Scan::class);
    }
}
