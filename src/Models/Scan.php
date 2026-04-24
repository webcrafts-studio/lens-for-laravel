<?php

namespace LensForLaravel\LensForLaravel\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Scan extends Model
{
    protected $table = 'lens_scans';

    protected $fillable = [
        'url',
        'scan_mode',
        'urls_scanned',
        'total_issues',
        'level_a_count',
        'level_aa_count',
        'level_aaa_count',
    ];

    protected $casts = [
        'urls_scanned' => 'array',
        'total_issues' => 'integer',
        'level_a_count' => 'integer',
        'level_aa_count' => 'integer',
        'level_aaa_count' => 'integer',
    ];

    protected static function booted(): void
    {
        static::deleting(function (Scan $scan) {
            $scan->issues()->delete();
        });
    }

    public function issues(): HasMany
    {
        return $this->hasMany(ScanIssue::class);
    }
}
